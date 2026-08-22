<?php

namespace App\Filament\Widgets;

use App\Contracts\CampaignBudgetService;
use App\Models\Campaign;
use App\Models\Clip;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * En-tête de la fiche campagne : consommation du budget en temps réel.
 *
 * Le restant est lu via le service, jamais directement en base : c'est la même
 * valeur que celle vue par le module clippeur.
 */
class CampaignBudgetOverview extends StatsOverviewWidget
{
    public ?Campaign $record = null;

    protected function getStats(): array
    {
        $campaign = $this->record;

        if (! $campaign) {
            return [];
        }

        $remaining = app(CampaignBudgetService::class)->remaining($campaign);
        $views = (int) Clip::where('campaign_id', $campaign->getKey())->sum('views_total');
        $clips = Clip::where('campaign_id', $campaign->getKey())->count();

        return [
            Stat::make('Budget total', static::euros($campaign->budget_total_cents))
                ->description($campaign->artist?->name)
                ->color('gray'),

            Stat::make('Consommé', static::euros($campaign->spent_cents))
                ->description($campaign->consumedPercent().' % du budget')
                ->color(match (true) {
                    $campaign->consumedPercent() >= 100 => 'danger',
                    $campaign->consumedPercent() >= 90 => 'warning',
                    default => 'success',
                }),

            Stat::make('Restant', static::euros($remaining))
                ->description($remaining === 0 ? 'Budget épuisé' : 'Disponible pour les clippeurs')
                ->color($remaining === 0 ? 'danger' : 'primary'),

            Stat::make('Vues générées', number_format($views, 0, ',', ' '))
                ->description(sprintf(
                    '%d clip%s · CPM réel %s',
                    $clips,
                    $clips > 1 ? 's' : '',
                    $views > 0 ? static::euros(intdiv($campaign->spent_cents * 1000, $views)) : '—',
                ))
                ->color('gray'),
        ];
    }

    protected static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
