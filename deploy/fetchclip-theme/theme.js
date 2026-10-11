/**
 * FetchClip : voile d'ouverture, barre de progression, premier chargement.
 *
 * Ce fichier ne vit pas dans le dépôt de FetchClip : Nginx l'injecte avant
 * la fermeture du corps, et le dépôt reste intact — `git pull` continue de
 * fonctionner sans conflit.
 *
 * Il n'ajoute aucune fonctionnalité et ne remplace rien. Il observe, il
 * habille, et il clique une fois à la place de la personne. Tout ce qu'il
 * fait est réversible en retirant une ligne de la configuration Nginx.
 */
(function () {
    'use strict';

    var sobre = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ─────────────── le voile d'ouverture ─────────────── */

    /*
     * Il couvre le temps où l'interface n'existe pas encore. Sans lui, on
     * voit la page se construire morceau par morceau — barre latérale vide,
     * puis liste des lives, puis grille — ce qui ressemble à un bug plutôt
     * qu'à un chargement.
     */
    var voile = document.createElement('div');
    voile.className = 'ca-voile';
    voile.setAttribute('aria-hidden', 'true');
    voile.innerHTML =
        '<span class="ca-marque">C</span>' +
        '<span class="ca-rail"></span>' +
        '<span class="ca-mot">Connexion à Twitch…</span>';

    document.body.appendChild(voile);

    var parti = false;

    function retirerLeVoile() {
        if (parti) return;
        parti = true;

        voile.classList.add('is-parti');
        // Retiré du document après la transition : laissé en place, il
        // intercepterait les clics malgré son opacité nulle.
        setTimeout(function () { voile.remove(); }, sobre ? 0 : 500);
    }

    /*
     * Deux conditions de sortie, et la seconde est un filet.
     *
     * Normalement les lives arrivent et le voile part. Mais si Twitch ne
     * répond pas, ou si une clé est fausse, la liste reste vide pour
     * toujours — et le voile avec elle. Un écran de chargement qui ne finit
     * jamais est la pire des pannes : on ne sait même pas quoi signaler.
     */
    var cible = document.getElementById('trending');

    if (cible) {
        var guetteur = new MutationObserver(function () {
            if (cible.querySelector('.side-item:not(.empty-row)')) {
                guetteur.disconnect();
                premierChargement();
                retirerLeVoile();
            }
        });

        guetteur.observe(cible, { childList: true, subtree: true });
    }

    setTimeout(retirerLeVoile, 6000);

    /* ─────────────── le premier chargement ─────────────── */

    /*
     * À l'arrivée, l'écran demandait de choisir un streamer. C'est une
     * question à laquelle la colonne de gauche répond déjà : le premier
     * live est, par construction, celui qui a le plus de spectateurs — donc
     * celui dont les clips sont les plus nombreux et les plus vus.
     *
     * On clique donc à sa place, une fois. Si la personne a une recherche
     * dans son adresse, ou a déjà tapé quelque chose, on ne touche à rien :
     * c'est son intention qui primerait.
     */
    function premierChargement() {
        var champ = document.getElementById('channels');
        var grille = document.getElementById('grid');

        if (!champ || champ.value.trim() !== '') return;
        // Une grille déjà remplie veut dire que l'application a restauré un
        // état : le remplacer effacerait ce que la personne regardait.
        if (grille && grille.querySelector('.card')) return;

        var premier = document.querySelector('#trending .side-item:not(.empty-row)');

        if (premier) premier.click();
    }

    /* ─────────────── la barre de progression ─────────────── */

    /*
     * Elle couvre les attentes qui suivent : une recherche, un
     * téléchargement, un rendu. Contrairement au voile, elle n'immobilise
     * rien — on continue de lire pendant qu'elle avance.
     *
     * Branchée sur `fetch` plutôt que sur les boutons : l'application en
     * compte des dizaines, et un bouton oublié aurait donné une attente
     * sans aucun signe.
     */
    var barre = document.createElement('div');
    barre.className = 'ca-barre';
    barre.setAttribute('aria-hidden', 'true');
    document.body.appendChild(barre);

    var enCours = 0;
    var avancement = 0;
    var minuteur = null;

    function demarrer() {
        enCours++;

        if (enCours > 1) return;

        avancement = 8;
        barre.classList.add('is-active');
        barre.style.width = avancement + '%';

        /*
         * Jusqu'à 90 % seulement, par paliers décroissants. Prétendre
         * connaître la fin d'un appel qu'on ne mesure pas produit une barre
         * qui stagne à 99 %, ce qui est pire que pas de barre : on croit
         * que c'est bloqué.
         */
        minuteur = setInterval(function () {
            avancement += Math.max(0.4, (90 - avancement) / 14);
            barre.style.width = Math.min(90, avancement) + '%';
        }, 220);
    }

    function finir() {
        enCours = Math.max(0, enCours - 1);

        if (enCours > 0) return;

        clearInterval(minuteur);
        barre.style.width = '100%';

        setTimeout(function () {
            barre.classList.remove('is-active');
            // La largeur n'est remise à zéro qu'une fois invisible, sinon on
            // voit la barre revenir en arrière.
            setTimeout(function () { barre.style.width = '0'; }, 320);
        }, 180);
    }

    var fetchOrigine = window.fetch;

    window.fetch = function () {
        demarrer();

        // `finally` plutôt que `then` : une requête en échec doit aussi
        // faire disparaître la barre, sans quoi elle reste à 90 % et l'écran
        // paraît figé alors que l'erreur est déjà affichée.
        return fetchOrigine.apply(this, arguments).finally(finir);
    };
})();
