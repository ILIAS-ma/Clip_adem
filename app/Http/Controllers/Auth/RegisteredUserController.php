<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\ValidTurnstile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', [
            // Le choix arrive en paramètre depuis la page d'accueil, sinon
            // clippeur par défaut : c'est le parcours le plus fréquent.
            'role' => $request->query('profil') === UserRole::Creator->value
                ? UserRole::Creator
                : UserRole::Clipper,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Piège à robots : un visiteur humain ne voit jamais ce champ, donc
        // ne le remplit jamais. On répond comme si l'inscription avait
        // réussi plutôt que de renvoyer une erreur qui aiderait le bot à
        // ajuster son script.
        if (filled($request->input('website'))) {
            return redirect()->route('register');
        }

        // Rôle absent : on retombe sur le profil le moins privilégié plutôt que
        // de rejeter la requête. La liste blanche ci-dessous reste la vraie
        // protection.
        $request->merge(['role' => $request->input('role', UserRole::Clipper->value)]);

        $validated = $request->validate([
            // Deux champs à la saisie — plus naturel pour un formulaire — mais
            // une seule colonne en base : le nom complet reste ce qui sert
            // partout ailleurs (versements, affichage), pas de raison de le
            // fragmenter côté modèle pour un simple choix de mise en page.
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // L'inscription publique ne peut créer qu'un clippeur ou un
            // créateur : les rôles du back-office ne se donnent pas par
            // formulaire, même en trafiquant la requête.
            'role' => ['required', Rule::in([UserRole::Clipper->value, UserRole::Creator->value])],

            // `requiredIf` et non `required` : sans clé de site, le widget
            // Cloudflare ne s'affiche pas, donc aucun jeton n'est envoyé et
            // l'inscription deviendrait impossible. La règle elle-même tolère
            // déjà l'absence de secret — les deux bouts doivent dégrader de la
            // même façon, sinon un déploiement sans clés bloque tout le monde
            // au lieu de laisser passer les robots.
            'cf-turnstile-response' => [
                Rule::requiredIf(filled(config('services.turnstile.site_key'))),
                new ValidTurnstile($request->ip()),
            ],
        ]);

        $user = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::from($validated['role']),
        ]);

        // Quand la vérification est suspendue, l'e-mail ne sert à rien — et son
        // envoi ferait échouer l'inscription entière si le serveur de mail est
        // absent. Le compte reste non vérifié : rétablir le contrôle plus tard
        // lui redemandera de confirmer, ce qui est le comportement correct.
        if (config('clipping.onboarding.require_email_verification')) {
            event(new Registered($user));
        }

        Auth::login($user);

        return redirect($user->role->homeRoute());
    }
}
