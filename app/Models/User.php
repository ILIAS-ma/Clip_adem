<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;

#[Fillable(['name', 'email', 'password', 'role', 'paypal_email'])]
#[Hidden(['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
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
        ];
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
}
