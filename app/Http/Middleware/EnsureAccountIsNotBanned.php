<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte banni est déconnecté, pas seulement masqué dans l'interface.
 *
 * Le bannissement est décidé côté modération admin ; sans ce contrôle, une
 * session déjà ouverte continuerait de fonctionner jusqu'à son expiration.
 */
class EnsureAccountIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_banned) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => trim('Votre compte a été suspendu. '.($user->ban_reason ?? '')),
            ]);
        }

        return $next($request);
    }
}
