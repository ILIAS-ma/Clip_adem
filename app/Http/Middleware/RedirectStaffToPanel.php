<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administrateurs et modérateurs travaillent dans le panel, pas dans l'espace
 * clippeur. Une seule règle, appliquée au groupe de routes, plutôt qu'un test
 * dispersé dans chaque contrôleur d'authentification de Breeze.
 */
class RedirectStaffToPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isStaff()) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
