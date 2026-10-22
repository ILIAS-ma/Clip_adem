<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité absents par défaut de Laravel.
 *
 * Aucun n'a d'effet visible pour un visiteur légitime : ils ferment des
 * portes que personne n'utilise en temps normal (être chargé dans une iframe
 * étrangère, laisser un navigateur deviner un type MIME, exposer la version
 * de PHP) plutôt que d'ajouter une protection contre une attaque précise.
 *
 * Volontairement absent d'ici : Content-Security-Policy. En poser une trop
 * stricte casse silencieusement des pages (Turnstile, Alpine, Vite en dev) ;
 * une trop permissive ne protège de rien. Elle mérite d'être construite et
 * testée à part, page par page, pas ajoutée à la volée avec le reste.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Empêche d'afficher le site dans une <iframe> étrangère : la seule
        // vraie parade au clickjacking (superposer un bouton invisible sur un
        // site légitime pour détourner un clic).
        $response->headers->set('X-Frame-Options', 'DENY');

        // Empêche un navigateur de deviner le type d'un fichier à partir de son
        // contenu plutôt que de son Content-Type déclaré — la faille classique
        // qui fait exécuter comme script un fichier « image » uploadé.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // N'envoie l'URL complète comme référent qu'aux pages du même site ;
        // seule l'origine part vers l'extérieur. Une URL de la plateforme peut
        // contenir un jeton ou un identifiant qui n'a rien à faire dans les
        // journaux d'un site tiers.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Personne n'a besoin de la caméra, du micro ou de la géolocalisation
        // ici : autant le dire au navigateur plutôt que de compter sur le fait
        // qu'aucune page ne les demandera jamais.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS uniquement sur une connexion déjà chiffrée : l'envoyer en HTTP
        // n'aurait aucun effet, et l'envoyer partout casserait l'accès en HTTP
        // simple d'un environnement de développement.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // La version exacte de PHP n'a aucune raison d'être publique : c'est
        // une liste de vulnérabilités connues offerte à qui la demande. PHP
        // l'ajoute lui-même via expose_php, en dehors du sac d'en-têtes de
        // Symfony : header_remove() de PHP est donc nécessaire en plus du
        // retrait côté Symfony, sinon il revient tel quel dans la réponse.
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }
}
