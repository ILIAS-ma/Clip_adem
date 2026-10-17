<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Payouts\LegalStatusService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Vérification du SIRET déclaré par un clippeur, depuis la liste ou la fiche.
 *
 * L'administrateur ouvre la fiche publique de l'établissement avant de
 * valider : le numéro doit exister, être actif, et le nom doit correspondre à
 * celui du compte. La clé de contrôle ne prouve que l'absence de faute de
 * frappe.
 */
class SiretActions
{
    public static function registryUrl(User $user): ?string
    {
        return $user->siret ? 'https://annuaire-entreprises.data.gouv.fr/etablissement/'.$user->siret : null;
    }

    public static function verify(): Action
    {
        return Action::make('verifySiret')
            ->label('Valider le SIRET')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (User $record) => $record->hasPendingSiret())
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => 'Valider le SIRET '.$record->siret)
            ->modalDescription(fn (User $record) => 'Vérifiez au registre que l’établissement est actif et au nom de '
                .$record->name.'. Le plafond annuel de retraits sera levé.')
            ->extraModalFooterActions(fn (User $record) => [
                Action::make('openRegistry')
                    ->label('Ouvrir le registre')
                    ->url(static::registryUrl($record), shouldOpenInNewTab: true),
            ])
            ->action(function (User $record) {
                app(LegalStatusService::class)->verify($record, auth()->user());

                Notification::make()->success()->title('SIRET validé')->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('rejectSiret')
            ->label('Refuser le SIRET')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (User $record) => $record->hasPendingSiret() || $record->isVerifiedBusiness())
            ->schema([
                Textarea::make('reason')
                    ->label('Motif, envoyé au clippeur')
                    ->placeholder('Ex. : établissement fermé, nom différent du compte.')
                    ->required()
                    ->maxLength(255)
                    ->rows(3),
            ])
            ->action(function (User $record, array $data) {
                app(LegalStatusService::class)->reject($record, $data['reason'], auth()->user());

                Notification::make()->success()->title('SIRET refusé, clippeur prévenu')->send();
            });
    }
}
