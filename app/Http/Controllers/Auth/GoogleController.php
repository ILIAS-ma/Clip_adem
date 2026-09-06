<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Exceptions\SocialProviderFailed;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\GoogleAuthProvider;
use App\Services\Referrals\ReferralService;
use App\Support\Auth\GoogleProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    public function __construct(
        protected GoogleAuthProvider $google,
        protected ReferralService $referrals,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->google->isConfigured()) {
            return redirect()->route('login')->withErrors([
                'email' => 'La connexion Google n’est pas encore configurée sur ce site.',
            ]);
        }

        // Jeton anti-CSRF du parcours OAuth : sans lui, un tiers pourrait faire
        // rattacher son propre compte Google à la session de la victime.
        $state = Str::random(40);
        $request->session()->put('google.state', $state);

        // Le rôle voulu et le code de parrainage doivent survivre à l'aller-
        // retour chez Google : ils ne reviendront pas dans le retour d'appel.
        $request->session()->put('google.role', $request->query('profil') === UserRole::Creator->value
            ? UserRole::Creator->value
            : UserRole::Clipper->value);

        return redirect()->away($this->google->redirectUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($error = $request->query('error')) {
            return redirect()->route('login')->withErrors([
                'email' => 'Connexion Google annulée ('.$error.').',
            ]);
        }

        $expected = $request->session()->pull('google.state');

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('login')->withErrors([
                'email' => 'Session expirée. Relancez la connexion Google.',
            ]);
        }

        try {
            $profile = $this->google->profileFrom((string) $request->query('code'));
        } catch (SocialProviderFailed $exception) {
            return redirect()->route('login')->withErrors(['email' => $exception->getMessage()]);
        }

        $user = $this->resolveUser($profile, $request);

        if ($user === null) {
            return redirect()->route('login')->withErrors([
                'email' => 'Ce compte Google ne peut pas être utilisé pour vous connecter. '
                    .'Connectez-vous avec votre mot de passe, puis liez Google depuis votre profil.',
            ]);
        }

        if ($user->is_banned) {
            return redirect()->route('login')->withErrors([
                'email' => 'Ce compte est suspendu.',
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended($user->role->homeRoute());
    }

    /**
     * Retrouve ou crée le compte derrière ce profil Google.
     *
     * `null` signifie « on refuse », et c'est le cas qui compte : une adresse
     * non vérifiée par Google ne doit JAMAIS rattacher un compte existant.
     * Sinon il suffirait de créer un compte Google déclarant l'adresse d'un
     * administrateur pour prendre sa place.
     */
    protected function resolveUser(GoogleProfile $profile, Request $request): ?User
    {
        // 1. Déjà connu par son identifiant Google : le cas normal.
        if ($user = User::where('google_id', $profile->googleId)->first()) {
            // `tap($user)->forceFill(...)->save()` renverrait le booléen de
            // `save()`, pas l'utilisateur : la forme à deux arguments est la
            // seule qui rende bien l'objet.
            return tap($user, fn (User $u) => $u->forceFill([
                'avatar_url' => $profile->avatarUrl,
            ])->save());
        }

        $byEmail = User::where('email', $profile->email)->first();

        // 2. Un compte existe avec cette adresse.
        if ($byEmail) {
            if (! $profile->emailVerified) {
                return null;
            }

            return tap($byEmail, fn (User $u) => $u->forceFill([
                'google_id' => $profile->googleId,
                'avatar_url' => $profile->avatarUrl,
                // Google a vérifié l'adresse : la revérifier nous-mêmes
                // n'apporterait rien qu'un e-mail de plus.
                'email_verified_at' => $u->email_verified_at ?? now(),
            ])->save());
        }

        // 3. Personne : on crée. Une adresse non vérifiée par Google repart
        // vers le parcours classique, qui saura la faire confirmer.
        if (! $profile->emailVerified) {
            return null;
        }

        $role = UserRole::tryFrom((string) $request->session()->pull('google.role')) ?? UserRole::Clipper;

        $user = User::create([
            'name' => $profile->displayName(),
            'email' => $profile->email,
            'role' => in_array($role, [UserRole::Clipper, UserRole::Creator], true) ? $role : UserRole::Clipper,
            // Pas de mot de passe : ce compte n'existe que par Google, et en
            // inventer un que personne ne connaît n'aiderait personne.
            'password' => null,
        ]);

        $user->forceFill([
            'google_id' => $profile->googleId,
            'avatar_url' => $profile->avatarUrl,
            'email_verified_at' => now(),
        ])->save();

        $this->referrals->attachFromRequest($user, $request);

        return $user;
    }
}
