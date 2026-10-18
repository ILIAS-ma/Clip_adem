<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Catégorie')
                ->columns(2)
                ->schema(static::components()),
        ]);
    }

    /**
     * Champs partagés avec la création à la volée depuis le formulaire de
     * campagne : une catégorie créée là doit valoir celle créée ici.
     *
     * @return list<TextInput>
     */
    public static function components(): array
    {
        return [
            // L'identifiant du lien de l'onglet se fabrique tout seul depuis
            // le nom (voir Category::booted()), et l'ordre des onglets suit
            // l'argent gagné : rien d'autre à saisir.
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
        ];
    }
}
