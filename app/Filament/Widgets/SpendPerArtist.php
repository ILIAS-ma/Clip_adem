<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Artists\ArtistResource;
use App\Services\Reporting\ReportingService;
use Filament\Widgets\Widget;

class SpendPerArtist extends Widget
{
    protected string $view = 'filament.widgets.spend-per-artist';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        return [
            'artists' => app(ReportingService::class)->spendPerArtist(8),
            'resourceUrl' => fn (int $id) => ArtistResource::getUrl('edit', ['record' => $id]),
        ];
    }
}
