<?php

namespace App\Filament\Resources\Clippers\Pages;

use App\Filament\Resources\Clippers\ClipperResource;
use Filament\Resources\Pages\ListRecords;

class ListClippers extends ListRecords
{
    protected static string $resource = ClipperResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
