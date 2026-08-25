<?php

namespace App\Filament\Widgets;

use App\Services\Reporting\ReportingService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Courbe de consommation du budget sur 30 jours, lue dans le grand livre.
 *
 * Les annulations y apparaissent en négatif : une journée sous zéro signale
 * une vague d'invalidations, ce qui est une information en soi.
 */
class BudgetConsumptionChart extends ChartWidget
{
    protected ?string $heading = 'Consommation du budget (30 jours)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $daily = app(ReportingService::class)->dailySpend(30);

        return [
            'datasets' => [
                [
                    'label' => 'Dépensé (€)',
                    'data' => $daily->map(fn (int $cents) => round($cents / 100, 2))->values()->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $daily->keys()
                ->map(fn (string $day) => Carbon::parse($day)->format('d/m'))
                ->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}
