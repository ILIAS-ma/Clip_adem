<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\SmsFailed;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PhoneVerification;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhoneVerificationController extends Controller
{
    public function __construct(protected PhoneVerification $verification) {}

    public function edit(Request $request): View
    {
        return view('auth.verify-phone', [
            'user' => $request->user(),
            'delai' => $this->verification->delaiRestant($request->user()),
            'pays' => PhoneNumber::INDICATIFS,
        ]);
    }

    /** Enregistre le numéro et envoie un code. */
    public function send(Request $request): RedirectResponse
    {
        $valide = $request->validate([
            'phone' => ['required', 'string', 'max:25'],
            'phone_country' => ['required', 'string', 'size:2'],
        ]);

        $normalise = PhoneNumber::normalise($valide['phone'], $valide['phone_country']);

        if ($normalise === null) {
            return back()->withErrors(['phone' => 'Ce numéro ne peut pas être lu. Vérifiez-le.']);
        }

        /*
         * Un numéro ne sert qu'un compte. C'est tout l'intérêt de cette
         * vérification : une adresse jetable s'obtient en dix secondes, une
         * ligne téléphonique non — c'est elle qui rend un bannissement
         * dissuasif. Sans ce contrôle, la même ligne couvrirait dix comptes.
         */
        $pris = User::where('phone', $normalise)
            ->whereKeyNot($request->user()->getKey())
            ->exists();

        if ($pris) {
            return back()->withErrors(['phone' => 'Ce numéro est déjà utilisé par un autre compte.']);
        }

        try {
            $this->verification->envoyer($request->user(), $valide['phone'], $valide['phone_country']);
        } catch (SmsFailed $e) {
            report($e);

            return back()->withErrors(['phone' => $e->isTemporary()
                ? 'L’envoi a échoué momentanément. Réessayez dans un instant.'
                : 'L’envoi de SMS n’est pas disponible. Contactez-nous.']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return redirect()->route('phone.verify')
            ->with('status', 'Un code vient de vous être envoyé par SMS.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $valide = $request->validate([
            'code' => ['required', 'string', 'size:'.PhoneVerification::LONGUEUR],
        ]);

        if (! $this->verification->verifier($request->user(), $valide['code'])) {
            // Message unique quelle que soit la cause — code faux, expiré, ou
            // trop d'essais. Préciser renseignerait qui cherche à deviner.
            return back()->withErrors(['code' => 'Code incorrect ou expiré. Demandez-en un nouveau.']);
        }

        /*
         * Retour là où la personne allait. La garde a retenu la page demandée
         * avant de rediriger ici : sans cela, un lien de campagne partagé
         * perdrait sa destination à la première vérification, et l'on
         * atterrirait sur son profil sans savoir pourquoi.
         */
        return redirect()->intended(route('profile.edit'))
            ->with('status', 'Votre numéro est vérifié.');
    }
}
