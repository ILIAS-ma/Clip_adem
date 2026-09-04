<?php

namespace App\Filament\Resources\Creators\Pages;

use App\Filament\Resources\Creators\CreatorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCreator extends CreateRecord
{
    protected static string $resource = CreatorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
