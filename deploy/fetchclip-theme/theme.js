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

    /* ─────────────── la barre du site ─────────────── */

    /*
     * L'outil occupe tout l'écran avec sa propre barre latérale : une fois
     * dedans, plus rien ne ramenait au site — ni au tableau de bord, ni aux
     * campagnes, ni aux messages. Seul le bouton « précédent » du navigateur
     * en sortait.
     *
     * On y remet la navigation du site, la même que partout ailleurs, dans
     * sa forme repliée : les mêmes icônes dans le même ordre, le nom au
     * survol. Repliée, parce que l'outil a déjà sa propre colonne et que
     * deux colonnes pleines lui mangeraient la moitié de l'écran.
     *
     * Les tracés viennent de NavigationComposer : une rubrique ajoutée là
     * doit l'être ici aussi.
     */
    var rubriques = [
        ['/dashboard', 'Tableau de bord', 'M4 5a1 1 0 011-1h5v7H4V5zm0 9h6v6H5a1 1 0 01-1-1v-5zm10-10h5a1 1 0 011 1v4h-6V4zm0 7h6v8a1 1 0 01-1 1h-5v-9z'],
        ['/campagnes', 'Campagnes', 'M3 10v4a1 1 0 001 1h3l5 4V5L7 9H4a1 1 0 00-1 1zm13.5-3a5 5 0 010 10M19 4a9 9 0 010 16'],
        ['/fetchclip', 'Echo Studio', 'M11 19a8 8 0 100-16 8 8 0 000 16zm10 2l-4.35-4.35M11 8v6m-3-3h6'],
        ['/mes-clips', 'Mes clips', 'M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm0 4h16M8 5v4m8-4v4m-6 4.5l4 2.5-4 2.5v-5z'],
        ['/mes-comptes', 'Mes comptes', 'M10 13a4 4 0 005.66 0l3-3a4 4 0 10-5.66-5.66l-1 1M14 11a4 4 0 00-5.66 0l-3 3a4 4 0 105.66 5.66l1-1'],
        ['/revenus', 'Revenus', 'M3 8a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm13 4a2 2 0 11-4 0 2 2 0 014 0z'],
        ['/classement', 'Classement', 'M4 20h4v-7H4v7zm6 0h4V5h-4v15zm6 0h4v-11h-4v11z'],
        ['/succes', 'Succès', 'M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.2l5.9-.9L12 3z'],
        ['/messages', 'Messages', 'M8 10h8M8 14h5m-9 6l2.5-3H18a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14z'],
    ];
    var compte = [
        ['/profile', 'Mon compte', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ['/revenus/paiement', 'Moyen de paiement', 'M3 8a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm0 3h18'],
    ];

    function lien(r) {
        var actif = r[0] === '/fetchclip';
        return '<a href="' + r[0] + '" class="ca-rail-lien' + (actif ? ' is-actif' : '') + '" data-libelle="' + r[1] + '"' +
            ' aria-label="' + r[1] + '"' + (actif ? ' aria-current="page"' : '') + '>' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="' + r[2] + '"/></svg></a>';
    }

    var rail = document.createElement('nav');
    rail.className = 'ca-rail';
    rail.setAttribute('aria-label', 'Navigation du site');
    rail.innerHTML =
        '<a href="/dashboard" class="ca-rail-marque" data-libelle="Echo Clip" aria-label="Echo Clip — tableau de bord"></a>' +
        '<div class="ca-rail-liens">' + rubriques.map(lien).join('') + '</div>' +
        '<div class="ca-rail-compte">' + compte.map(lien).join('') + '</div>';
    document.body.insertBefore(rail, document.body.firstChild);
    document.body.classList.add('ca-avec-rail');

    // Le nom de l'outil dans sa propre barre latérale.
    var titreOutil = document.querySelector('.brand-text h1');
    if (titreOutil) titreOutil.textContent = 'Echo Studio';

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
        return fetchOrigine.apply(this, arguments)
            .then(function (reponse) {
                if (reponse.status === 401) sessionExpiree();

                return reponse;
            })
            .finally(finir);
    };

    /* ─────────────── la session ─────────────── */

    /*
     * Nginx vérifie la session par une sous-requête dont la réponse ne
     * revient jamais au navigateur : le cookie de session n'était donc
     * jamais renouvelé pendant qu'on travaillait ici. Deux heures après la
     * dernière page du site, on était déconnecté en plein montage.
     *
     * On appelle donc une route du site toutes les dix minutes : sa réponse,
     * elle, arrive au navigateur avec un cookie neuf. Tant que l'onglet est
     * ouvert, la session tient.
     */
    function entretenir() {
        fetchOrigine('/_studio-ping', { credentials: 'same-origin' })
            .then(function (r) {
                // Déjà expirée : mieux vaut le dire maintenant que lors du
                // prochain clic, au milieu d'un montage.
                if (r.status === 401 || r.redirected) sessionExpiree();
            })
            .catch(function () { /* réseau coupé : on réessaiera */ });
    }

    setInterval(entretenir, 10 * 60 * 1000);

    // Un onglet en arrière-plan voit ses minuteurs ralentis par le
    // navigateur : on rattrape au retour plutôt que d'attendre le suivant.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') entretenir();
    });

    /*
     * Session expirée : on le dit, une fois, et on propose d'y remédier.
     *
     * Pas de redirection automatique : elle effacerait l'écran sans
     * prévenir, et ce qui était en cours de réglage avec lui. Un bandeau
     * laisse le choix du moment.
     */
    var bandeauAffiche = false;

    function sessionExpiree() {
        if (bandeauAffiche) return;
        bandeauAffiche = true;

        var bandeau = document.createElement('div');
        bandeau.className = 'ca-session';
        bandeau.setAttribute('role', 'alert');
        bandeau.innerHTML =
            '<span><strong>Session expirée.</strong> Les actions ne passent plus ' +
            'tant que tu ne t’es pas reconnecté.</span>' +
            '<a href="/connexion-studio" class="btn primary sm">Se reconnecter</a>';

        document.body.appendChild(bandeau);
    }

    /* ─────────────── les erreurs, remontées ─────────────── */

    /*
     * L'outil tourne entièrement dans le navigateur : une exception y
     * reste dans une console que personne ne regarde, et ce qui nous
     * parvient est « ça ne marche pas ». On les renvoie au serveur, où
     * elles rejoignent les journaux du site.
     *
     * Les deux évènements comptent : `error` pour ce qui casse dans un
     * gestionnaire, `unhandledrejection` pour une promesse rejetée sans
     * `catch` — et c'est souvent celle-là, puisque tout l'outil est fait
     * d'appels asynchrones.
     */
    var dejaVues = {};

    function rapporter(message, source, trace) {
        // Une boucle d'erreurs enverrait mille requêtes par seconde : on ne
        // signale chaque message qu'une fois par chargement de page.
        var cle = String(message).slice(0, 120);

        if (dejaVues[cle]) return;
        dejaVues[cle] = true;

        var corps = new FormData();
        corps.append('message', String(message || '').slice(0, 300));
        corps.append('source', String(source || '').slice(0, 200));
        corps.append('trace', String(trace || '').slice(0, 1500));

        // `fetchOrigine` et non `fetch` : passer par notre enveloppe ferait
        // monter la barre de progression pour un envoi invisible.
        fetchOrigine('/_studio-log', {
            method: 'POST',
            body: corps,
        }).catch(function () { /* le rapport d'erreur ne doit pas en créer une */ });
    }

    window.addEventListener('error', function (e) {
        rapporter(e.message, e.filename + ':' + e.lineno, e.error && e.error.stack);
    });

    window.addEventListener('unhandledrejection', function (e) {
        var r = e.reason;

        rapporter(r && r.message ? r.message : String(r), 'promesse', r && r.stack);
    });

    /* ─────────────── le fichier arrive sur le PC ─────────────── */

    /*
     * FetchClip a été écrit pour tourner sur la machine du clippeur : son
     * bouton « Télécharger » range le clip dans un dossier du disque, et
     * « Ouvrir le dossier » ouvre l'explorateur. Ici l'outil tourne sur le
     * serveur — le clip arrivait dans un dossier du serveur, l'explorateur
     * s'ouvrait sur le serveur, et rien n'atteignait jamais le PC. Pour le
     * clippeur, le téléchargement « ne marchait pas ».
     *
     * On rétablit donc le geste d'origine : dès que le serveur a fini — un
     * clip récupéré, un montage rendu, une série ou un lot terminés — le
     * fichier part dans les téléchargements du navigateur.
     */
    function enregistrer(dossier, nom) {
        if (!nom) return;
        var lien = document.createElement('a');
        lien.href = '/api/file?folder=' + dossier + '&name=' + encodeURIComponent(nom);
        lien.download = nom;
        lien.style.display = 'none';
        document.body.appendChild(lien);
        lien.click();
        lien.remove();
    }

    // Une tâche est suivie par des relevés répétés : ses fichiers ne
    // partent qu'une fois, au premier relevé qui la dit terminée.
    var dejaEnvoyes = {};

    function envoyerUneFois(cle, dossier, nom) {
        if (dejaEnvoyes[cle]) return;
        dejaEnvoyes[cle] = true;
        enregistrer(dossier, nom);
    }

    var fetchSansFichiers = window.fetch;

    window.fetch = function (entree, options) {
        var adresse = typeof entree === 'string' ? entree : (entree && entree.url) || '';
        var methode = ((options && options.method) || 'GET').toUpperCase();

        // Ouvrir un dossier du serveur ne montre rien à personne.
        if (adresse.indexOf('/api/open-folder') === 0) {
            return Promise.resolve(new Response('{"ok":true}', {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }));
        }

        var promesse = fetchSansFichiers.apply(this, arguments);

        var suivie = (methode === 'POST' && (adresse === '/api/download' || adresse === '/api/render'))
            || adresse.indexOf('/api/series/status') === 0
            || adresse.indexOf('/api/batch/status') === 0;

        if (suivie) {
            promesse.then(function (reponse) {
                if (!reponse.ok) return;

                reponse.clone().json().then(function (d) {
                    if (adresse === '/api/download' && d.file) {
                        enregistrer('clips', String(d.file).split(/[\\/]/).pop());
                    } else if (adresse === '/api/render' && d.name) {
                        enregistrer('montages', d.name);
                    } else if (d.job && d.job.state === 'termine') {
                        (d.job.results || []).forEach(function (r) {
                            envoyerUneFois(d.job.id + r.name, 'montages', r.name);
                        });
                        (d.job.items || []).forEach(function (i) {
                            if (i.state === 'terminé') envoyerUneFois(d.job.id + i.name, 'montages', i.name);
                        });
                    }
                }).catch(function () {});
            }).catch(function () {});
        }

        return promesse;
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
