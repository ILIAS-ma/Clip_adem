<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un créateur sans fiche n'a rien à voir : ses statistiques se rattachent à
 * cette fiche, et c'est elle que l'administrateur associe aux campagnes.
 */
class EnsureCreatorProfileExists
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isCreator() && ! $user->creator) {
            return redirect()->route('creator.profile.create');
        }

        return $next($request);
    }
}
