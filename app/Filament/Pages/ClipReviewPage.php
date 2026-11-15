<?php

namespace App\Filament\Pages;

use App\Enums\ClipStatus;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use App\Services\Moderation\ClipModerationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Vérification finale d'une campagne, clippeur par clippeur.
 *
 * Pendant la campagne, les clips sont validés automatiquement. À la fin, l'équipe ouvre chaque
 * clippeur du classement, regarde ses clips en galerie, confirme qu'ils parlent bien du bon
 * streamer, ou refuse ceux qui n'ont rien à faire là. Rien n'est bloqué : c'est un second regard.
 */
class ClipReviewPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Vérification finale';

    protected static ?string $title = 'Vérification finale des clips';

    protected static ?string $slug = 'verification-clips';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected string $view = 'filament.pages.clip-review';

    #[Url]
    public ?int $campaignId = null;

    #[Url]
    public ?int $userId = null;

    public string $reason = 'Ce clip ne concerne pas le streamer de la campagne.';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public function mount(): void
    {
        $this->campaignId ??= Campaign::query()->orderByDesc('starts_at')->orderByDesc('id')->value('id');
    }

    public function updatedCampaignId(): void
    {
        $this->userId = null;
    }

    /** @return array<int, string> */
    public function campaigns(): array
    {
        return Campaign::query()->orderByDesc('id')->pluck('title', 'id')->all();
    }

    public function campaign(): ?Campaign
    {
        return $this->campaignId ? Campaign::find($this->campaignId) : null;
    }

    /**
     * Les clippeurs de la campagne, du plus vu au moins vu, avec l'avancement de la vérification.
     *
     * @return Collection<int, object>
     */
    public function clippers(): Collection
    {
        if (! $this->campaignId) {
            return collect();
        }

        $approved = ClipStatus::Approved->value;

        $rows = Clip::query()
            ->where('campaign_id', $this->campaignId)
            ->whereIn('status', [ClipStatus::Approved, ClipStatus::Invalidated])
            ->selectRaw('user_id,
                SUM(CASE WHEN status = ? THEN views_total ELSE 0 END) as views,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as clips,
                SUM(CASE WHEN status = ? AND reviewed_at IS NOT NULL THEN 1 ELSE 0 END) as reviewed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as removed', [
                $approved, $approved, $approved, ClipStatus::Invalidated->value,
            ])
            ->groupBy('user_id')
            ->orderByDesc('views')
            ->get();

        $users = User::whereIn('id', $rows->pluck('user_id'))->get(['id', 'name', 'pseudo'])->keyBy('id');

        return $rows->values()->map(fn ($row, int $i) => (object) [
            'rank' => $i + 1,
            'user_id' => (int) $row->user_id,
            'name' => ($users[$row->user_id]->pseudo ?? null) ?: ($users[$row->user_id]->name ?? '—'),
            'views' => (int) $row->views,
            'clips' => (int) $row->clips,
            'reviewed' => (int) $row->reviewed,
            'removed' => (int) $row->removed,
        ]);
    }

    /** @return Collection<int, Clip> */
    public function clips(): Collection
    {
        if (! $this->campaignId || ! $this->userId) {
            return collect();
        }

        return Clip::query()
            ->where('campaign_id', $this->campaignId)
            ->where('user_id', $this->userId)
            ->whereIn('status', [ClipStatus::Approved, ClipStatus::Invalidated])
            ->orderByDesc('views_total')
            ->get();
    }

    public function open(int $userId): void
    {
        $this->userId = $userId;
    }

    /** Passe au clippeur suivant du classement, pour enchaîner sans revenir à la liste. */
    public function next(): void
    {
        $list = $this->clippers()->pluck('user_id')->all();
        $at = array_search($this->userId, $list, true);

        $this->userId = $at === false ? ($list[0] ?? null) : ($list[$at + 1] ?? null);
    }

    public function verify(int $clipId): void
    {
        $this->mark(Clip::where('campaign_id', $this->campaignId)->whereKey($clipId)->where('status', ClipStatus::Approved)->get());
    }

    public function unverify(int $clipId): void
    {
        Clip::where('campaign_id', $this->campaignId)->whereKey($clipId)
            ->update(['reviewed_at' => null, 'reviewed_by' => null]);
    }

    /** Tous les clips encore à vérifier de ce clippeur. */
    public function verifyAll(): void
    {
        $this->mark(Clip::where('campaign_id', $this->campaignId)->where('user_id', $this->userId)
            ->where('status', ClipStatus::Approved)->whereNull('reviewed_at')->get());
    }

    public function reject(int $clipId, ClipModerationService $moderation): void
    {
        $reason = trim($this->reason);

        if ($reason === '') {
            Notification::make()->danger()->title('Écris un motif de refus.')->send();

            return;
        }

        $clip = Clip::where('campaign_id', $this->campaignId)->whereKey($clipId)->where('status', ClipStatus::Approved)->first();

        if (! $clip) {
            return;
        }

        $moderation->invalidate($clip, $reason, auth()->user());

        Notification::make()->success()->title('Clip refusé')
            ->body('Ses vues sortent du classement et ses gains sont retirés.')->send();
    }

    /** @param Collection<int, Clip> $clips */
    protected function mark(Collection $clips): void
    {
        foreach ($clips as $clip) {
            $clip->forceFill(['reviewed_at' => now(), 'reviewed_by' => auth()->id()])->save();
        }
    }
}
