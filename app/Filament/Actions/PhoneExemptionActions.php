<?php

namespace App\Filament\Actions;

use App\Enums\ModerationAction;
use App\Enums\UserRole;
use App\Models\ModerationLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Les comptes clippeurs sans numéro de téléphone.
 *
 * Le numéro vérifié est la barrière contre les comptes multiples : un fraudeur banni revient sinon sous
 * une autre adresse en une minute. Lever cette barrière n'est donc pas un réglage banal. Elle reste
 * entière pour les inscriptions publiques ; seul le super-admin dispense un compte, et chaque dispense
 * laisse une trace datée dans le journal de modération.
 */
class PhoneExemptionActions
{
    /** Ouvrir un compte clippeur sans numéro (liste des clippeurs). */
    public static function create(): Action
    {
        return Action::make('createWithoutPhone')
            ->label('Créer un clippeur sans téléphone')
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false)
            ->modalHeading('Créer un compte clippeur sans numéro de téléphone')
            ->modalDescription('Le compte est ouvert tout de suite, e-mail confirmé, sans code SMS. Transmets-lui son mot de passe : il pourra le changer ensuite.')
            ->modalSubmitActionLabel('Créer le compte')
            ->schema([
                TextInput::make('name')->label('Nom')->required()->maxLength(255),
                TextInput::make('pseudo')->label('Pseudo')->required()->maxLength(255)->unique(User::class, 'pseudo'),
                TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255)->unique(User::class, 'email'),
                Select::make('country')->label('Pays')->options(['FR' => 'France', 'BE' => 'Belgique', 'CH' => 'Suisse', 'CA' => 'Canada', 'MA' => 'Maroc', 'DZ' => 'Algérie', 'TN' => 'Tunisie'])->default('FR')->required(),
                TextInput::make('password')->label('Mot de passe')->password()->revealable()->required()->rule(Password::min(10))
                    ->default(fn () => Str::password(14, symbols: false))
                    ->helperText('Généré pour toi : copie-le avant de valider, il ne sera plus affiché ensuite.'),
            ])
            ->action(function (array $data) {
                $user = User::create([
                    'name' => $data['name'],
                    'pseudo' => $data['pseudo'],
                    'country' => $data['country'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => UserRole::Clipper,
                ]);

                // Le super-admin répond de ce compte : l'e-mail est confirmé d'office, le téléphone dispensé.
                $user->forceFill(['email_verified_at' => now(), 'phone_exempt_at' => now()])->save();

                ModerationLog::record(ModerationAction::PhoneExemptionGranted, $user, auth()->user(), 'Compte créé sans numéro de téléphone');

                Notification::make()->success()
                    ->title('Compte créé : '.$user->email)
                    ->body('Il peut se connecter avec cet e-mail et le mot de passe saisi, sans code SMS.')
                    ->send();
            });
    }

    /** Dispenser un compte existant de numéro, ou l'exiger de nouveau (fiche du clippeur). */
    public static function toggle(): Action
    {
        return Action::make('togglePhoneExemption')
            ->label(fn (User $record) => $record->isPhoneExempt() ? 'Exiger le téléphone' : 'Dispenser du téléphone')
            ->icon(fn (User $record) => $record->isPhoneExempt() ? 'heroicon-o-phone' : 'heroicon-o-phone-x-mark')
            ->color('gray')
            ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false)
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => $record->isPhoneExempt() ? 'Exiger de nouveau le numéro' : 'Dispenser ce compte de numéro')
            ->modalDescription(fn (User $record) => $record->isPhoneExempt()
                ? 'Le clippeur devra confirmer un numéro de téléphone à sa prochaine visite.'
                : 'Ce compte pourra utiliser le site sans numéro de téléphone vérifié. À réserver aux comptes que tu connais.')
            ->action(function (User $record) {
                if ($record->isPhoneExempt()) {
                    $record->forceFill(['phone_exempt_at' => null])->save();
                    ModerationLog::record(ModerationAction::PhoneExemptionRemoved, $record, auth()->user());
                    Notification::make()->success()->title('Le numéro est de nouveau exigé')->send();

                    return;
                }

                $record->forceFill(['phone_exempt_at' => now()])->save();
                ModerationLog::record(ModerationAction::PhoneExemptionGranted, $record, auth()->user());
                Notification::make()->success()->title('Compte dispensé de numéro de téléphone')->send();
            });
    }
}
