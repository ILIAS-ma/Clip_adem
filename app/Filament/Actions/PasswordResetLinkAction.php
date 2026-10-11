<?php

namespace App\Filament\Actions;

use App\Enums\ModerationAction;
use App\Models\ModerationLog;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Password;

/**
 * Un lien de réinitialisation du mot de passe, à transmettre à la main.
 *
 * Le site n'envoie pas d'e-mail : sans ce bouton, un mot de passe oublié
 * bloquait le compte pour de bon. L'administrateur crée le lien et le fait
 * parvenir par le moyen qu'il veut — la messagerie n'y suffit pas, la
 * personne n'est justement pas connectée.
 *
 * Le lien ouvre le compte à qui le détient. D'où trois garde-fous : réservé
 * au super-administrateur, jamais pour un compte de l'équipe (un modérateur
 * ne doit pas pouvoir prendre la main sur un autre), et chaque lien créé est
 * inscrit au journal de modération. Il expire et ne sert qu'une fois — c'est
 * le mécanisme standard de Laravel, inchangé.
 */
class PasswordResetLinkAction
{
    /**
     * @param  Closure(Model): ?User  $userOf  le compte visé, depuis la ligne du tableau
     */
    public static function make(Closure $userOf): Action
    {
        return Action::make('passwordResetLink')
            ->label('Lien de réinitialisation')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->visible(fn (Model $record) => static::allowed($userOf($record)))
            ->requiresConfirmation()
            ->modalHeading('Créer un lien de réinitialisation ?')
            ->modalDescription('Le lien permet de choisir un nouveau mot de passe pour ce compte. '
                .'Valable '.config('auth.passwords.users.expire').' minutes, une seule fois. '
                .'Ne le donnez qu’à la personne concernée.')
            ->modalSubmitActionLabel('Créer le lien')
            ->action(function (Model $record) use ($userOf) {
                $user = $userOf($record);

                abort_unless(static::allowed($user), 403);

                $lien = static::linkFor($user);

                ModerationLog::record(ModerationAction::PasswordResetLinkIssued, $user, auth()->user());

                // Une notification qui reste à l'écran jusqu'à fermeture : le
                // lien n'est montré qu'une fois, il ne doit pas disparaître
                // avant d'avoir été copié.
                Notification::make()
                    ->title('Lien créé pour '.$user->displayName())
                    ->body($lien)
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    public static function allowed(?User $user): bool
    {
        return $user !== null
            && (auth()->user()?->isSuperAdmin() ?? false)
            && ! $user->role->isStaff();
    }

    public static function linkFor(User $user): string
    {
        return route('password.reset', [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
        ]);
    }
}
