<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Clippers\ClipperResource;
use App\Services\Reporting\ReportingService;
use Filament\Widgets\Widget;

class TopClippers extends Widget
{
    protected string $view = 'filament.widgets.top-clippers';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        return [
            'clippers' => app(ReportingService::class)->topClippers(8),
            'resourceUrl' => fn (int $id) => ClipperResource::getUrl('view', ['record' => $id]),
        ];
    }
}
