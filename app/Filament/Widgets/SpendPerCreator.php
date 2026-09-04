<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Creators\CreatorResource;
use App\Services\Reporting\ReportingService;
use Filament\Widgets\Widget;

class SpendPerCreator extends Widget
{
    protected string $view = 'filament.widgets.spend-per-creator';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        return [
            'creators' => app(ReportingService::class)->spendPerCreator(8),
            'resourceUrl' => fn (int $id) => CreatorResource::getUrl('edit', ['record' => $id]),
        ];
    }
}
