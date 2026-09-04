<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Support\Iban;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;

#[Fillable(['name', 'pseudo', 'country', 'email', 'password', 'role', 'paypal_email'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'iban'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            // Un dump de base ne doit pas suffire à régénérer les codes 2FA
            // d'un administrateur.
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'profile_completed_at' => 'datetime',
            'payout_method' => PayoutMethod::class,
            // Donnée bancaire : un dump de base ne doit pas la livrer en clair.
            // Les quatre derniers chiffres vivent à part, eux en clair, pour
            // l'affichage et le rapprochement d'un virement.
            'iban' => 'encrypted',
        ];
    }

    // ------------------------------------------------------------------
    // Espace clippeur
    // ------------------------------------------------------------------

    public function isClipper(): bool
    {
        return $this->role === UserRole::Clipper;
    }

    public function isCreator(): bool
    {
        return $this->role === UserRole::Creator;
    }

    /** Fiche créateur pilotée par ce compte. */
    public function creator(): HasOne
    {
        return $this->hasOne(Creator::class);
    }

    /** Nom affiché publiquement : le pseudo s'il existe, le prénom sinon. */
    public function displayName(): string
    {
        return $this->pseudo ?: $this->name;
    }

    /**
     * Ce qu'il manque pour participer et être payé.
     *
     * Vérifié à chaque requête plutôt qu'au seul moment du retrait : découvrir
     * qu'il manque une adresse PayPal après avoir généré 200 000 vues est la
     * meilleure façon de perdre un clippeur.
     *
     * @return array<int, string>
     */
    public function missingProfileFields(): array
    {
        return collect([
            'pseudo' => $this->pseudo,
            'country' => $this->country,
            // Un seul manque possible côté paiement, quel que soit le mode :
            // l'écran n'a pas à expliquer « il manque un IBAN » à quelqu'un qui
            // a choisi PayPal.
            'payout' => $this->payoutDestination(),
        ])->filter(fn ($value) => blank($value))->keys()->all();
    }

    public function hasCompleteProfile(): bool
    {
        return $this->missingProfileFields() === [];
    }

    // ------------------------------------------------------------------
    // Accès au back-office
    // ------------------------------------------------------------------

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isStaff();
    }

    // ------------------------------------------------------------------
    // Double authentification TOTP (obligatoire pour le personnel)
    // ------------------------------------------------------------------

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  ?array<string>  $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(CampaignParticipation::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff() && ! $this->is_banned;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin && ! $this->is_banned;
    }

    /** Total gagné, tous clips et toutes campagnes confondus, en centimes. */
    public function earnedCents(): int
    {
        return (int) $this->clips()->sum('earned_cents');
    }

    /** Montant déjà demandé ou versé, donc immobilisé, en centimes. */
    public function lockedPayoutCents(): int
    {
        $locked = array_map(
            fn (PayoutStatus $status) => $status->value,
            array_filter(PayoutStatus::cases(), fn (PayoutStatus $status) => $status->locksBalance()),
        );

        return (int) $this->payouts()->whereIn('status', $locked)->sum('amount_cents');
    }

    /**
     * Solde retirable, en centimes. Toujours calculé, jamais stocké :
     * un solde dénormalisé finit toujours par diverger du grand livre.
     */
    public function availableBalanceCents(): int
    {
        return max(0, $this->earnedCents() - $this->lockedPayoutCents());
    }

    // ------------------------------------------------------------------
    // Moyen de paiement
    // ------------------------------------------------------------------

    /** PayPal par défaut : c'est ce que faisaient les comptes créés avant le RIB. */
    public function payoutMethod(): PayoutMethod
    {
        return $this->payout_method ?? PayoutMethod::PayPal;
    }

    /**
     * Où part l'argent, en clair, pour l'appel au prestataire.
     *
     * `null` signifie « aucune destination utilisable » : c'est ce qui sert de
     * test partout ailleurs, plutôt que de reposer la question du mode.
     */
    public function payoutDestination(): ?string
    {
        return match ($this->payoutMethod()) {
            PayoutMethod::PayPal => $this->paypal_email ?: null,
            PayoutMethod::BankTransfer => $this->iban ?: null,
        };
    }

    /** La même chose, mais montrable : l'IBAN n'apparaît jamais en entier. */
    public function payoutDestinationLabel(): ?string
    {
        return match ($this->payoutMethod()) {
            PayoutMethod::PayPal => $this->paypal_email ?: null,
            PayoutMethod::BankTransfer => $this->iban_last4
                ? Iban::mask($this->iban_last4)
                : null,
        };
    }

    public function hasPayoutDestination(): bool
    {
        return $this->payoutDestination() !== null;
    }
}
