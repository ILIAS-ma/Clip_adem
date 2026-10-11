<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\ValidTurnstile;
use App\Services\Referrals\ReferralService;
use App\Support\PhoneNumber;
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
    public function __construct(
        protected ReferralService $referrals,
    ) {}

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

            /*
             * Le numéro est demandé dès l'inscription, pas plus tard : c'est
             * le seul identifiant réellement coûteux à multiplier, et le
             * réclamer après coup revient à laisser entrer dix comptes avant
             * de poser la question. Il n'est pas vérifié ici — l'envoi d'un
             * SMS pendant la création du compte ferait échouer l'inscription
             * entière si le prestataire ne répond pas.
             */
            'phone' => ['required', 'string', 'max:25'],
            'phone_country' => ['required', 'string', 'size:2', Rule::in(array_keys(PhoneNumber::INDICATIFS))],

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

        $numero = PhoneNumber::normalise($validated['phone'], $validated['phone_country']);

        if ($numero === null) {
            throw ValidationException::withMessages([
                'phone' => 'Ce numéro ne peut pas être lu. Vérifiez-le.',
            ]);
        }

        /*
         * Unicité vérifiée ici en plus de la contrainte de base : celle-ci
         * lèverait une QueryException, c'est-à-dire une page d'erreur, là où
         * la personne attend simplement qu'on lui dise que ce numéro est déjà
         * pris. Le message reste vague sur le compte concerné — préciser
         * permettrait de savoir qui est inscrit.
         */
        if (User::where('phone', $numero)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'Ce numéro est déjà utilisé par un autre compte.',
            ]);
        }

        $user = User::create([
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'phone' => $numero,
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

        // Le code peut venir du champ, de l'URL, ou du cookie posé à l'arrivée
        // sur un lien de parrainage consulté dix minutes plus tôt.
        $this->referrals->attachFromRequest($user, $request);

        Auth::login($user);

        /*
         * Droit à la vérification quand elle est exigée : la personne vient de
         * saisir son numéro, c'est le moment où elle a son téléphone en main.
         * L'envoyer d'abord sur son espace la ferait rebondir au premier clic,
         * sans comprendre pourquoi.
         */
        if (config('clipping.onboarding.require_phone_verification')) {
            return redirect()->route('phone.verify');
        }

        return redirect($user->role->homeRoute());
    }
}
