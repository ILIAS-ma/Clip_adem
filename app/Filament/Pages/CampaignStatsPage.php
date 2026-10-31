<?php

namespace App\Filament\Pages;

use App\Models\Campaign;
use App\Services\Campaigns\CampaignStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Les statistiques d'une campagne, et leur export Excel.
 *
 * On choisit la campagne, on lit les chiffres clés et le détail par clippeur, et on
 * télécharge le classeur complet : résumé, classements, clippeurs, clips, jours, modération.
 */
class CampaignStatsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Statistiques';

    protected static ?string $title = 'Statistiques des campagnes';

    protected static ?string $slug = 'statistiques';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected string $view = 'filament.pages.campaign-stats';

    public ?int $campaignId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public function mount(): void
    {
        // La campagne en cours la plus récente, à défaut la dernière créée.
        $this->campaignId = (Campaign::query()->orderByDesc('starts_at')->orderByDesc('id')->value('id'));
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

    /** @return array<string, mixed>|null */
    public function stats(): ?array
    {
        $campaign = $this->campaign();

        return $campaign ? app(CampaignStats::class)->gather($campaign) : null;
    }

    public function export(): ?BinaryFileResponse
    {
        $campaign = $this->campaign();

        if (! $campaign) {
            return null;
        }

        $stats = app(CampaignStats::class);

        return response()->download($stats->export($campaign), $stats->filename($campaign))->deleteFileAfterSend(true);
    }
}
