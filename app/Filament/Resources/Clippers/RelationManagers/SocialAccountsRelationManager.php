<?php

namespace App\Filament\Resources\Clippers\RelationManagers;

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Services\Social\SocialAccountLinker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
            ->recordActions([
                /*
                 * Libère un compte resté sur un profil dont le clippeur n'a
                 * plus l'accès — un profil de test, un mot de passe perdu.
                 * Sans ce bouton, le compte restait bloqué : il fallait se
                 * connecter à l'ancien profil pour le délier.
                 *
                 * Les clips déjà publiés restent à leur auteur, avec leurs
                 * gains : délier ne rend que la possibilité de relier le
                 * compte ailleurs.
                 */
                Action::make('unlink')
                    ->label('Délier')
                    ->icon('heroicon-o-link-slash')
                    ->color('danger')
                    ->visible(fn (SocialAccount $record) => $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading(fn (SocialAccount $record) => 'Délier @'.$record->handle.' ?')
                    ->modalDescription('Le compte pourra être relié à un autre profil. Les clips déjà '
                        .'publiés restent à ce clippeur, avec leurs gains ; le relevé des vues '
                        .'s’arrête tant que le compte n’est pas relié à nouveau.')
                    ->modalSubmitActionLabel('Délier')
                    ->action(function (SocialAccount $record) {
                        app(SocialAccountLinker::class)->unlink($record);

                        Notification::make()
                            ->title('@'.$record->handle.' est délié.')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
