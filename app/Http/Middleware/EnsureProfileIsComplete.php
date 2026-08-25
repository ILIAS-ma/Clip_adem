<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force la complétion du profil avant de laisser participer à une campagne.
 *
 * Le contrôle est fait à chaque requête plutôt qu'au seul moment du retrait :
 * découvrir qu'il manque une adresse PayPal après avoir généré 200 000 vues est
 * la meilleure façon de perdre un clippeur.
 */
class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isClipper() && ! $user->hasCompleteProfile()) {
            return redirect()
                ->route('profile.complete')
                ->with('status', 'profile-incomplete');
        }

        return $next($request);
    }
}
