<?php

namespace App\Filament\Pages;

use App\Enums\CampaignStatus;
use App\Enums\PayoutStatus;
use App\Exceptions\PayoutRefused;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\User;
use App\Services\Payouts\PayoutService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Les paiements d'une campagne terminée : qui est payé, combien, et où l'argent doit partir.
 *
 * Les clippeurs ne demandent rien. À la fin d'une campagne, l'équipe ouvre cette page, vérifie le classement,
 * envoie l'argent de son côté (virement, PayPal), puis marque chaque clippeur comme payé. Sa cagnotte baisse
 * d'autant et il est prévenu. Réservé au super-admin : les coordonnées de paiement y sont en clair.
 */
class CampaignPayments extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Paiements des campagnes';

    protected static ?string $title = 'Paiements des campagnes';

    protected static ?string $slug = 'paiements-campagnes';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected string $view = 'filament.pages.campaign-payments';

    #[Url]
    public ?int $campaignId = null;

    /** Référence du virement, notée sur chaque paiement marqué : facultative. */
    public string $reference = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->campaignId ??= Campaign::query()
            ->whereIn('status', [CampaignStatus::Completed->value, CampaignStatus::Exhausted->value])
            ->orWhere(fn ($q) => $q->whereNotNull('ends_at')->where('ends_at', '<', now()))
            ->orderByDesc('ends_at')->orderByDesc('id')
            ->value('id');
    }

    /** @return array<int, string> les campagnes terminées d'abord, puis les autres */
    public function campaigns(): array
    {
        return Campaign::query()->orderByDesc('id')->get()
            ->mapWithKeys(fn (Campaign $c) => [$c->id => '#'.$c->id.' · '.$c->title.($c->hasEnded() || $c->status === CampaignStatus::Exhausted ? '' : ' (en cours)')])
            ->all();
    }

    public function campaign(): ?Campaign
    {
        return $this->campaignId ? Campaign::find($this->campaignId) : null;
    }

    /** Une campagne dont les gains sont définitifs : terminée, clôturée ou épuisée. */
    public function isFinished(): bool
    {
        $campaign = $this->campaign();

        return $campaign !== null && ($campaign->hasEnded() || $campaign->status === CampaignStatus::Exhausted);
    }

    /** Un classement dont les prix n'ont pas été attribués : rien n'est encore à payer pour les gagnants. */
    public function prizesPending(): bool
    {
        $campaign = $this->campaign();

        return $campaign !== null && $campaign->isContest() && $campaign->prizes_paid_at === null && $campaign->allRewards() !== [];
    }

    /**
     * Une ligne par clippeur qui a gagné quelque chose sur la campagne, du plus gagnant au moins gagnant.
     *
     * @return Collection<int, object>
     */
    public function rows(): Collection
    {
        $campaign = $this->campaign();

        if (! $campaign) {
            return collect();
        }

        $earned = Clip::where('campaign_id', $campaign->getKey())
            ->selectRaw('user_id, SUM(earned_cents) as earned, COUNT(*) as clips')
            ->groupBy('user_id')
            ->havingRaw('SUM(earned_cents) > 0')
            ->orderByDesc('earned')
            ->get();

        $paid = Payout::where('campaign_id', $campaign->getKey())
            ->whereIn('status', array_map(fn (PayoutStatus $s) => $s->value, array_filter(PayoutStatus::cases(), fn (PayoutStatus $s) => $s->locksBalance())))
            ->selectRaw('user_id, SUM(amount_cents) as paid')
            ->groupBy('user_id')
            ->pluck('paid', 'user_id');

        $users = User::whereIn('id', $earned->pluck('user_id'))->get()->keyBy('id');

        return $earned->values()->map(function ($row, int $i) use ($users, $paid) {
            $user = $users[$row->user_id];
            $alreadyPaid = (int) ($paid[$row->user_id] ?? 0);
            $due = max(0, (int) $row->earned - $alreadyPaid);

            return (object) [
                'rank' => $i + 1,
                'user_id' => (int) $row->user_id,
                'name' => ($user->pseudo ?: $user->name),
                'clips' => (int) $row->clips,
                'earned' => (int) $row->earned,
                'paid' => $alreadyPaid,
                'due' => $due,
                'method' => $user->hasPayoutDestination() ? $user->payoutMethod()->label() : null,
                'destination' => $user->payoutDestination(),
                'banned' => (bool) $user->is_banned,
            ];
        });
    }

    public function pay(int $userId): void
    {
        $campaign = $this->campaign();

        if (! $campaign || ! $this->isFinished()) {
            return;
        }

        try {
            $payout = app(PayoutService::class)->payByTeam(User::findOrFail($userId), $campaign, auth()->user(), trim($this->reference) ?: null);
        } catch (PayoutRefused $exception) {
            Notification::make()->danger()->title('Paiement impossible')->body($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Payé : '.number_format($payout->amount_cents / 100, 2, ',', ' ').' €')->send();
    }

    /** Marque payés tous les clippeurs qui ont quelque chose à recevoir et un moyen de paiement. */
    public function payAll(): void
    {
        if (! $this->isFinished()) {
            return;
        }

        $done = 0;
        $skipped = [];

        foreach ($this->rows()->where('due', '>', 0) as $row) {
            try {
                app(PayoutService::class)->payByTeam(User::findOrFail($row->user_id), $this->campaign(), auth()->user(), trim($this->reference) ?: null);
                $done++;
            } catch (PayoutRefused $exception) {
                $skipped[] = $row->name.' : '.$exception->getMessage();
            }
        }

        $done > 0
            ? Notification::make()->success()->title($done.' paiement'.($done > 1 ? 's' : '').' enregistré'.($done > 1 ? 's' : ''))
                ->body($skipped === [] ? null : count($skipped).' non payé(s) : '.implode(' · ', array_slice($skipped, 0, 3)))->send()
            : Notification::make()->warning()->title('Personne à payer')->body($skipped === [] ? null : implode(' · ', array_slice($skipped, 0, 3)))->send();
    }
}
