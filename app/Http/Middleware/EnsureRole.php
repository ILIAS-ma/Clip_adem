<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chaque rôle a son espace, et n'entre pas dans celui des autres.
 *
 * On redirige plutôt qu'on interdit : un artiste qui suit un lien vers
 * `/dashboard` a fait une erreur d'adresse, pas une tentative d'intrusion —
 * l'envoyer chez lui vaut mieux qu'un 403 sans issue.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        if (! in_array($user->role, $allowed, true)) {
            return redirect($user->role->homeRoute());
        }

        return $next($request);
    }
}
