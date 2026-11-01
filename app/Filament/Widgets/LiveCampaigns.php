<?php

namespace App\Filament\Widgets;

use App\Enums\CampaignStatus;
use App\Enums\ContestMetric;
use App\Filament\Pages\CampaignStatsPage;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use App\Models\Clip;
use App\Services\Campaigns\ContestService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Les campagnes en cours, une carte chacune : quand elles finissent, ce qu'elles ont produit
 * aujourd'hui et au total, et pour un classement, les trois premiers en direct.
 */
class LiveCampaigns extends Widget
{
    protected string $view = 'filament.widgets.live-campaigns';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    protected function getViewData(): array
    {
        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->orderByRaw('ends_at IS NULL, ends_at')
            ->get()
            ->reject(fn (Campaign $campaign) => $campaign->isContest() && $campaign->contestIsOver());

        $ids = $campaigns->pluck('id');

        $totals = Clip::query()
            ->whereIn('campaign_id', $ids)
            ->where('status', 'approved')
            ->groupBy('campaign_id')
            ->select('campaign_id', DB::raw('COUNT(*) as clips'), DB::raw('COUNT(DISTINCT user_id) as clippers'), DB::raw('COALESCE(SUM(views_total), 0) as views'))
            ->get()
            ->keyBy('campaign_id');

        $today = Clip::query()
            ->whereIn('campaign_id', $ids)
            ->where('submitted_at', '>=', now()->startOfDay())
            ->groupBy('campaign_id')
            ->select('campaign_id', DB::raw('COUNT(*) as clips'))
            ->pluck('clips', 'campaign_id');

        $contest = app(ContestService::class);

        return [
            'campaigns' => $campaigns->map(function (Campaign $campaign) use ($totals, $today, $contest) {
                $row = $totals[$campaign->getKey()] ?? null;

                return [
                    'title' => $campaign->title,
                    'kind' => $campaign->isContest() ? 'Classement' : 'Paiement aux vues',
                    // Une campagne encore « active » dont la date est passée attend d'être clôturée : on le dit.
                    'ends' => match (true) {
                        $campaign->ends_at === null => 'Sans date de fin',
                        $campaign->ends_at->isPast() => 'Date de fin dépassée '.$campaign->ends_at->diffForHumans().' · à clôturer',
                        default => 'Se termine '.$campaign->ends_at->diffForHumans().' · '.$campaign->ends_at->timezone(config('app.timezone'))->format('d/m à H\hi'),
                    },
                    'soon' => $campaign->ends_at !== null && $campaign->ends_at->between(now(), now()->addDay()),
                    'clips' => (int) ($row->clips ?? 0),
                    'clippers' => (int) ($row->clippers ?? 0),
                    'views' => (int) ($row->views ?? 0),
                    'today' => (int) ($today[$campaign->getKey()] ?? 0),
                    'top' => $campaign->isContest()
                        ? $contest->standings($campaign, 3, ContestMetric::Views)->map(fn (object $r) => ['name' => $r->name, 'views' => (int) $r->views])->all()
                        : [],
                    'stats' => CampaignStatsPage::getUrl(['campaignId' => $campaign->getKey()]),
                    'edit' => CampaignResource::getUrl('edit', ['record' => $campaign]),
                ];
            })->values()->all(),
        ];
    }
}
