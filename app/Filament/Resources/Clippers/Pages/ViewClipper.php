<?php

namespace App\Filament\Resources\Clippers\Pages;

use App\Filament\Resources\Clippers\ClipperResource;
use Filament\Resources\Pages\ViewRecord;

class ViewClipper extends ViewRecord
{
    protected static string $resource = ClipperResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
