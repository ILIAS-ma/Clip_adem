<?php

namespace App\Filament\Resources\Clippers\Schemas;

use App\Filament\Actions\SiretActions;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClipperInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Compte')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Nom'),
                    TextEntry::make('email')->label('E-mail'),
                    TextEntry::make('paypal_email')
                        ->label('PayPal')
                        ->placeholder('Non renseigné'),
                    TextEntry::make('created_at')->label('Inscrit le')->date('d/m/Y'),
                    TextEntry::make('is_banned')
                        ->label('Statut')
                        ->badge()
                        ->state(fn (User $record) => $record->is_banned ? 'Banni' : 'Actif')
                        ->color(fn (User $record) => $record->is_banned ? 'danger' : 'success'),
                    TextEntry::make('ban_reason')
                        ->label('Motif du bannissement')
                        ->placeholder('—')
                        ->visible(fn (User $record) => $record->is_banned),
                ]),

            Section::make('Rémunération')
                ->columns(3)
                ->schema([
                    TextEntry::make('earned')
                        ->label('Total gagné')
                        ->state(fn (User $record) => static::euros($record->earnedCents())),

                    TextEntry::make('locked')
                        ->label('Demandé ou versé')
                        ->state(fn (User $record) => static::euros($record->lockedPayoutCents())),

                    TextEntry::make('balance')
                        ->label('Solde disponible')
                        ->state(fn (User $record) => static::euros($record->availableBalanceCents()))
                        ->weight('bold')
                        // Le solde est toujours calculé, jamais stocké : un
                        // solde dénormalisé finit par diverger du grand livre.
                        ->helperText('Gains − retraits demandés ou versés.'),
                ]),

            Section::make('Statut légal')
                ->columns(3)
                ->schema([
                    TextEntry::make('legal_status')
                        ->label('Statut déclaré')
                        ->state(fn (User $record) => $record->legalStatus()->label()),

                    TextEntry::make('siret')
                        ->label('SIRET')
                        ->placeholder('—')
                        ->copyable()
                        ->url(fn (User $record) => SiretActions::registryUrl($record), shouldOpenInNewTab: true),

                    TextEntry::make('siret_state')
                        ->label('Vérification')
                        ->badge()
                        ->state(fn (User $record) => match (true) {
                            $record->isVerifiedBusiness() => 'Vérifié le '.$record->siret_verified_at->format('d/m/Y'),
                            $record->hasPendingSiret() => 'À vérifier',
                            $record->siret_rejection_reason !== null => 'Refusé : '.$record->siret_rejection_reason,
                            default => 'Sans objet',
                        })
                        ->color(fn (User $record) => match (true) {
                            $record->isVerifiedBusiness() => 'success',
                            $record->hasPendingSiret() => 'warning',
                            $record->siret_rejection_reason !== null => 'danger',
                            default => 'gray',
                        }),
                ]),
        ]);
    }

    protected static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }
}
