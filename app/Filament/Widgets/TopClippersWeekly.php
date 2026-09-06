<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Clippers\ClipperResource;
use App\Services\Reporting\ReportingService;
use Filament\Widgets\Widget;

/**
 * Distinct de TopClippers (gains cumulés depuis toujours) : celui-ci répond
 * à « qui est actif cette semaine », pas « qui rapporte le plus au total ».
 * Un classement uniquement en cumulé masquerait un clippeur récent en pleine
 * forme derrière d'anciens qui ne publient plus.
 */
class TopClippersWeekly extends Widget
{
    protected string $view = 'filament.widgets.top-clippers-weekly';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        return [
            'clippers' => app(ReportingService::class)->topClippersThisWeek(10),
            'resourceUrl' => fn (int $id) => ClipperResource::getUrl('view', ['record' => $id]),
        ];
    }
}
