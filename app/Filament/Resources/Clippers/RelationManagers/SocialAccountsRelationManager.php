<?php

namespace App\Filament\Resources\Clippers\RelationManagers;

use App\Enums\Platform;
use App\Models\SocialAccount;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SocialAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'socialAccounts';

    protected static ?string $title = 'Comptes réseaux liés';

    public function form(Schema $schema): Schema
    {
        // Les comptes sont liés par OAuth depuis l'espace clippeur.
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('handle')
            ->columns([
                TextColumn::make('platform')
                    ->label('Plateforme')
                    ->badge()
                    ->formatStateUsing(fn (Platform $state) => $state->label()),

                TextColumn::make('handle')
                    ->label('Compte')
                    ->formatStateUsing(fn (?string $state) => $state ? '@'.$state : '—')
                    ->weight('bold'),

                TextColumn::make('followers_count')
                    ->label('Abonnés')
                    ->alignEnd()
                    ->formatStateUsing(fn (?int $state) => $state === null
                        ? '—'
                        : number_format($state, 0, ',', ' ')),

                TextColumn::make('clips_count')
                    ->label('Clips')
                    ->counts('clips')
                    ->alignEnd(),

                TextColumn::make('verified_at')
                    ->label('Vérifié')
                    ->badge()
                    ->state(fn (SocialAccount $record) => $record->verified_at ? 'Oui' : 'Non')
                    ->color(fn (SocialAccount $record) => $record->verified_at ? 'success' : 'gray'),

                TextColumn::make('is_active')
                    ->label('Actif')
                    ->badge()
                    ->state(fn (SocialAccount $record) => $record->is_active ? 'Oui' : 'Non')
                    ->color(fn (SocialAccount $record) => $record->is_active ? 'success' : 'danger'),
            ])
            ->headerActions([])
            ->recordActions([]);
    }
}
