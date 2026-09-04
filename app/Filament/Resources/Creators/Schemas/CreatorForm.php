<?php

namespace App\Filament\Resources\Creators\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CreatorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom de scène')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set, $context) => $context === 'create'
                            ? $set('slug', Str::slug((string) $state))
                            : null),

                    TextInput::make('slug')
                        ->label('Identifiant URL')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    FileUpload::make('avatar_path')
                        ->label('Photo')
                        ->image()
                        ->imageEditor()
                        ->directory('creators')
                        ->columnSpanFull(),

                    Textarea::make('bio')
                        ->label('Biographie')
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make('Réseaux')
                ->columns(2)
                ->schema([
                    TextInput::make('spotify_url')
                        ->label('Spotify')
                        ->url()
                        ->prefixIcon('heroicon-o-musical-note'),

                    TextInput::make('tiktok_handle')
                        ->label('TikTok')
                        ->prefix('@'),

                    TextInput::make('instagram_handle')
                        ->label('Instagram')
                        ->prefix('@'),

                    TextInput::make('youtube_handle')
                        ->label('YouTube')
                        ->prefix('@'),
                ]),

            Section::make('Back-office')
                ->schema([
                    Toggle::make('is_active')
                        ->label('Créateur actif')
                        ->helperText('Un créateur inactif n\'apparaît plus dans le sélecteur de création de campagne.')
                        ->default(true),

                    Textarea::make('internal_notes')
                        ->label('Notes internes')
                        ->helperText('Jamais visible par les clippeurs.')
                        ->rows(3),
                ]),
        ]);
    }
}
