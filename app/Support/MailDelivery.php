<?php

namespace App\Support;

/**
 * L'envoi d'e-mails part-il réellement ?
 *
 * Même exigence que pour les SMS : ne jamais annoncer un message qui n'est
 * pas parti. Deux situations se confondaient à l'écran et menaient toutes
 * deux à « lien envoyé » :
 *
 *  - aucun prestataire configuré, le message n'est qu'écrit dans un journal ;
 *  - un prestataire configuré mais injoignable, et là c'était une page 500 —
 *    le cas vécu, un SMTP local qui n'existe pas sur le serveur.
 *
 * Quelqu'un qui a oublié son mot de passe attend son lien, rafraîchit sa
 * boîte, et conclut que le site est cassé. Il l'est, mais ailleurs.
 */
class MailDelivery
{
    /**
     * Les pilotes qui n'envoient rien hors du serveur.
     *
     * `array` n'y figure pas, et c'est délibéré : c'est le pilote de la suite
     * de tests, où l'on vérifie précisément que les notifications partent. Le
     * confondre avec une absence de configuration reviendrait à neutraliser
     * les tests qui gardent l'envoi. Personne ne configure `array` en
     * production ; `log`, en revanche, est le réglage qu'on laisse derrière
     * soi en attendant un prestataire — c'est celui qui doit alerter.
     */
    protected const FACTICES = ['log', 'null'];

    public static function isActive(): bool
    {
        return ! in_array((string) config('mail.default'), self::FACTICES, true);
    }

    /** Ce qu'on dit quand rien ne peut partir. */
    public static function inactiveMessage(): string
    {
        return "L'envoi d'e-mails n'est pas encore activé sur la plateforme. "
            .'Rien ne vous a été envoyé — contactez-nous et nous le ferons à la main.';
    }

    /** Ce qu'on dit quand le prestataire n'a pas répondu. */
    public static function failedMessage(): string
    {
        return "L'envoi a échoué. Ce n'est pas votre adresse qui est en cause : "
            .'réessayez dans quelques minutes, ou contactez-nous.';
    }
}
