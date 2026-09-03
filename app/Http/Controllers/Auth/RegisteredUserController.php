<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
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
            'role' => $request->query('profil') === UserRole::Artist->value
                ? UserRole::Artist
                : UserRole::Clipper,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // Rôle absent : on retombe sur le profil le moins privilégié plutôt que
        // de rejeter la requête. La liste blanche ci-dessous reste la vraie
        // protection.
        $request->merge(['role' => $request->input('role', UserRole::Clipper->value)]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],

            // L'inscription publique ne peut créer qu'un clippeur ou un
            // artiste : les rôles du back-office ne se donnent pas par
            // formulaire, même en trafiquant la requête.
            'role' => ['required', Rule::in([UserRole::Clipper->value, UserRole::Artist->value])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
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
