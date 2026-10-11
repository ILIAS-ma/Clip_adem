/**
 * Connexion temps réel au serveur Reverb, pour la messagerie.
 *
 * Chargée par le site et par le back-office. Livewire s'y abonne de lui-même
 * dès qu'un composant déclare un écouteur `echo-private:` — il faut seulement
 * que `window.Echo` existe avant qu'il démarre.
 *
 * Sans clé (développement local, tests), rien n'est créé : les pages de
 * messagerie se rafraîchissent alors par leur relevé régulier, plus lent
 * mais suffisant.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const key = import.meta.env.VITE_REVERB_APP_KEY;

if (key) {
    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
