<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Les campagnes de la catégorie restent en place, simplement
            // sans catégorie (clé étrangère en « set null »).
            DeleteAction::make(),
        ];
    }
}
