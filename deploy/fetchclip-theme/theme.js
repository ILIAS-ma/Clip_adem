/**
 * FetchClip : voile d'ouverture, barre de progression, premier chargement.
 *
 * Ce fichier ne vit pas dans le dépôt de FetchClip : Nginx l'injecte avant
 * la fermeture du corps, et le dépôt reste donc intact — `git pull` continue
 * de fonctionner sans conflit.
 *
 * Il n'ajoute aucune fonctionnalité et ne remplace rien. Il observe, il
 * habille, et il lance une recherche à la place de la personne. Tout ce
 * qu'il fait se retire en supprimant une ligne de la configuration Nginx.
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
     *
     * Il se retire vers le bas, en découvrant la page : le mouvement dit
     * « c'est prêt, regarde dessous » là où une simple disparition ne dit
     * rien du tout.
     */
    var voile = document.createElement('div');
    voile.className = 'ca-voile';
    voile.setAttribute('aria-hidden', 'true');
    voile.innerHTML =
        '<div class="ca-centre">' +
        '<span class="ca-marque">C</span>' +
        '<span class="ca-rail"></span>' +
        '<span class="ca-mot">Connexion à Twitch…</span>' +
        '</div>';

    document.body.appendChild(voile);
    document.body.classList.add('ca-fige');

    var parti = false;

    function retirerLeVoile() {
        if (parti) return;
        parti = true;

        document.body.classList.remove('ca-fige');
        document.body.classList.add('ca-entre');
        voile.classList.add('is-parti');

        // Retiré du document après la transition : laissé en place, il
        // intercepterait les clics malgré son opacité nulle.
        setTimeout(function () { voile.remove(); }, sobre ? 0 : 700);
    }

    /* ─────────────── le premier chargement ─────────────── */

    /*
     * À l'arrivée, l'écran restait vide en demandant de choisir un streamer.
     *
     * Deux causes, et la seconde est la plus traître. Sans recherche
     * précédente, personne n'avait cliqué. Mais avec une recherche
     * précédente, l'application remet le pseudo dans le champ sans relancer
     * la recherche : on voyait donc « nico_la » écrit, et rien en dessous —
     * ce qui donne l'impression que l'outil a cherché et n'a rien trouvé.
     *
     * Les deux cas sont traités : on relance ce qui était en cours, sinon on
     * prend le premier live. Le premier live est, par construction, celui
     * qui a le plus de spectateurs — donc celui dont les clips sont les plus
     * nombreux et les plus vus.
     */
    var lance = false;

    function premierChargement() {
        if (lance) return;

        var champ = document.getElementById('channels');
        var grille = document.getElementById('results');
        var formulaire = document.getElementById('search-form');

        if (!champ || !formulaire) return;

        // Une grille déjà remplie veut dire que la recherche a eu lieu :
        // la relancer effacerait ce que la personne regarde.
        if (grille && grille.querySelector('.card')) {
            lance = true;

            return;
        }

        if (champ.value.trim() !== '') {
            lance = true;
            formulaire.requestSubmit();

            return;
        }

        var premier = document.querySelector('#trending .side-item:not(.empty-row)');

        if (premier) {
            lance = true;
            premier.click();
        }
    }

    /* ─────────────── Kick, écarté ─────────────── */

    /*
     * Kick est retiré de l'interface. Son API de clips n'est pas
     * documentée, Cloudflare y rejette l'empreinte TLS du serveur, et le
     * téléchargement y échoue une fois sur deux — un bouton qui marche par
     * intermittence coûte plus de confiance qu'il n'apporte de clips.
     *
     * Le choix est aussi forcé en mémoire : quelqu'un qui avait sélectionné
     * Kick avant ce retrait serait resté coincé dessus, avec un sélecteur
     * désormais invisible.
     */
    function ecarterKick() {
        // La clé est « platform », sans préfixe, et sa valeur est du JSON :
        // la chaîne stockée vaut donc "kick", guillemets compris.
        try {
            localStorage.setItem('platform', '"twitch"');
        } catch (e) { /* navigation privée : rien à écrire */ }

        /*
         * L'application a déjà lu ce réglage au démarrage, avant ce script :
         * si Kick était retenu, elle y est passée. On reclique donc sur
         * Twitch, ce qui rejoue proprement son propre `setPlatform` au lieu
         * de bricoler son état interne.
         */
        var twitch = document.querySelector('.plat[data-platform="twitch"]');

        if (twitch && !twitch.classList.contains('active')) twitch.click();
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

    /* ─────────────── l'enchaînement ─────────────── */

    /*
     * On attend que l'application ait fini de démarrer, et le signal est son
     * bandeau d'état : il n'est rempli qu'après la réponse de /api/status,
     * donc après que les champs ont été restaurés et la plateforme choisie.
     * Agir avant revenait à lancer une recherche que l'initialisation
     * écrasait aussitôt.
     */
    var etat = document.getElementById('status');

    function quandPret() {
        ecarterKick();
        premierChargement();
        retirerLeVoile();
    }

    if (etat) {
        var guetteur = new MutationObserver(function () {
            if (etat.querySelector('.pill')) {
                guetteur.disconnect();
                quandPret();
            }
        });

        guetteur.observe(etat, { childList: true, subtree: true });
    }

    /*
     * Filet de sécurité. Si Twitch ne répond pas, ou si une clé est fausse,
     * le bandeau d'état reste vide pour toujours — et le voile avec lui. Un
     * écran de chargement sans fin est la pire des pannes : on ne sait même
     * pas quoi signaler.
     */
    setTimeout(quandPret, 7000);
})();
