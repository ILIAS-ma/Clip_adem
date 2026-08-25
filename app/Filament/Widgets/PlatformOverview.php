<?php

namespace App\Filament\Widgets;

use App\Services\Reporting\ReportingService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $stats = app(ReportingService::class)->globalStats();

        return [
            Stat::make('Budget engagé', static::euros($stats['budget_engaged_cents']))
                ->description($stats['active_campaigns'].' campagne(s) active(s)')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color('gray'),

            Stat::make('Consommé', static::euros($stats['spent_cents']))
                ->description(static::euros($stats['remaining_cents']).' restants')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            // L'écart entre consommé et versé est la dette réelle envers les
            // clippeurs : c'est le chiffre qui doit être provisionné.
            Stat::make('Dû aux clippeurs', static::euros($stats['owed_cents']))
                ->description(static::euros($stats['paid_cents']).' déjà versés')
                ->descriptionIcon('heroicon-m-arrow-up-right')
                ->color($stats['owed_cents'] > 0 ? 'warning' : 'gray'),

            Stat::make('Vues générées', number_format($stats['views'], 0, ',', ' '))
                ->description($stats['real_cpm_cents'] === null
                    ? $stats['clips'].' clip(s)'
                    : 'CPM réel '.static::euros($stats['real_cpm_cents']))
                ->descriptionIcon('heroicon-m-eye')
                ->color('primary'),
        ];
    }

    protected static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
