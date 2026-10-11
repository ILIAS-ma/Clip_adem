<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un numéro confirmé avant de laisser entrer.
 *
 * C'est le seul identifiant réellement coûteux à multiplier : une adresse
 * jetable s'obtient en dix secondes, une ligne téléphonique non. Sans lui, un
 * fraudeur banni revient sous un autre compte dans la minute, et ce sont les
 * budgets des créateurs qui paient ses clips.
 *
 * Le contrôle est fait à chaque requête, pas au seul moment du retrait :
 * découvrir qu'il faut un numéro après avoir monté dix clips est la meilleure
 * façon de perdre un clippeur.
 *
 * Les routes de vérification elles-mêmes vivent hors des groupes gardés, sinon
 * la redirection boucle sur elle-même.
 */
class EnsurePhoneIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * Le contrôle est lu ici, à chaque requête, et non au moment où les
         * routes se composent. La différence n'est pas théorique : les gardes
         * d'embarquement existantes sont filtrées à l'enregistrement des
         * routes, donc figées pour toute la durée du processus — c'est ce qui
         * les rend impossibles à éprouver dans les deux états. Celle-ci se
         * laisse suspendre et rétablir à volonté.
         */
        if (! config('clipping.onboarding.require_phone_verification')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && ! $user->hasVerifiedPhone()) {
            /*
             * On retient la page demandée : après la confirmation, la personne
             * repart où elle allait plutôt que sur un tableau de bord qu'elle
             * n'avait pas demandé. Sans cela, un lien de campagne partagé
             * perdrait sa destination à la première vérification.
             */
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()
                ->route('phone.verify')
                ->with('status', 'phone-unverified');
        }

        return $next($request);
    }
}
