<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

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
            TextInput::make('name')
                ->label('Nom')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $context) => $context === 'create'
                    ? $set('slug', Str::slug((string) $state))
                    : null),

            // Clé du lien partagé du classement : /classement?categorie=…
            TextInput::make('slug')
                ->label('Identifiant URL')
                ->required()
                ->unique('categories', 'slug', ignoreRecord: true)
                ->maxLength(255)
                ->dehydrateStateUsing(fn ($state) => Str::slug((string) $state)),

            TextInput::make('position')
                ->label('Ordre d’affichage')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0)
                ->helperText('Les onglets du classement sont triés du plus petit au plus grand.'),
        ];
    }
}
