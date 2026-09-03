# Clip Adem

Plateforme de clipping : un administrateur crée des campagnes de promotion pour
des artistes avec un budget total et un taux de rémunération par plateforme.
Des clippeurs publient des clips, et sont payés selon les vues générées, jusqu'à
épuisement du budget — premier arrivé, premier servi.

Le dépôt est partagé entre deux périmètres :

| Périmètre | Responsable | État |
|---|---|---|
| Espace admin + moteur de campagne / budget | Ilias | Livré |
| Espace clippeur complet : auth, catalogue, comptes, clips, revenus | Ilias | Livré |
| Espace artiste : suivi des campagnes et du rendement | Ilias | Livré |
| Validation des intégrations sur les API réelles | Anas | En attente des clés |

## Démarrage

```bash
composer install
cp .env.example .env
php artisan key:generate

# MySQL 8 requis (voir « Pourquoi pas SQLite » plus bas)
mysql -u root -e "CREATE DATABASE clip; CREATE DATABASE clip_testing;"

php artisan migrate --seed
php artisan serve
```

La connexion est à la racine `/`, le panel admin sur `/admin`. Chaque rôle est
ensuite renvoyé vers son propre espace — `/dashboard` pour un clippeur,
`/artiste` pour un artiste, `/admin` pour le staff. La page de présentation du
fonctionnement reste sur `/presentation`.

Comptes de démonstration (mot de passe `password`) :

| Compte | Rôle |
|---|---|
| `admin@clip-adem.test` | super-administrateur |
| `moderateur@clip-adem.test` | modérateur |
| `lina@clippeur.test` | clippeur, avec clips et gains |
| `karim@clippeur.test` | clippeur, avec un clip aux vues suspectes |
| `nayra@artiste.test` | artiste, avec une campagne en cours |

### Passages obligés, suspendables

Trois contrôles peuvent être suspendus pour parcourir l'interface sans obstacle
pendant le développement :

| Variable `.env` | Effet quand `false` |
|---|---|
| `REQUIRE_EMAIL_VERIFICATION` | L'e-mail n'a plus besoin d'être confirmé |
| `REQUIRE_COMPLETE_PROFILE` | Pseudo, pays et PayPal ne bloquent plus |
| `REQUIRE_ADMIN_2FA` | Le panel n'impose plus de scanner un QR code |

Aucun code n'est commenté ni supprimé : les contrôles restent en place, et la
suite de tests **les force à `true`** pour continuer de les vérifier. Un bandeau
orange s'affiche sur toutes les pages tant qu'un contrôle est suspendu — sans
lui, un contrôle désactivé « le temps de voir l'interface » finit en production.

À rétablir avant toute mise en ligne : sans vérification d'e-mail, une adresse
jetable rend le bannissement inopérant ; sans 2FA, un compte admin compromis
donne accès aux paiements.

### E-mails en développement

Les e-mails partent vers **Mailpit**, qui les capture au lieu de les envoyer à
de vraies boîtes :

```bash
C:/laragon/bin/mailpit/mailpit.exe --smtp 127.0.0.1:1025 --listen 127.0.0.1:8025
```

Boîte de réception sur <http://127.0.0.1:8025>. L'écran « confirmez votre
e-mail » rappelle cette adresse en local, sinon il serait un cul-de-sac.

Les e-mails d'authentification sont traduits dans `AppServiceProvider` : un
clippeur francophone qui reçoit « Verify Email Address » le prend pour du spam,
et c'est la première cause de comptes jamais activés.

## La règle la plus importante du projet

> **Le budget d'une campagne ne se modifie que par `CampaignBudgetService`.**

Aucun autre code — jamais, y compris le module clippeur — n'écrit sur
`campaigns.spent_cents`, `clips.paid_views` ou `clips.earned_cents`. Ces
colonnes ne figurent volontairement pas dans les `#[Fillable]` des modèles.

### Le contrat côté module clippeur

```php
use App\Contracts\CampaignBudgetService;
use App\Models\BudgetTransaction;

// Après avoir synchronisé les vues et inséré un clip_view_snapshot :
$result = app(CampaignBudgetService::class)->creditViews(
    clip: $clip,
    newTotalViews: $snapshot->views,
    idempotencyKey: BudgetTransaction::snapshotKey($clip->id, $snapshot->id),
);

$result->outcome;        // CreditOutcome : credited, capped, no_budget_left, …
$result->creditedCents;  // ce qui a réellement été payé
$result->remainingCents; // budget restant après l'opération
```

Les autres méthodes :

| Méthode | Usage |
|---|---|
| `remaining($campaign)` | Budget restant en centimes, pour affichage |
| `quote($clip, $views)` | Simulation, n'écrit rien : « ce clip vous rapportera X € » |
| `creditViews(...)` | La seule méthode qui débite |
| `reverseClip($clip, $reason, $by)` | Modération : annule un clip et rend le budget |
| `acceptsNewClips($campaign)` | Le clippeur peut-il encore poster ? |

Le service est lié à l'interface dans `AppServiceProvider` : type-hintez
`CampaignBudgetService`, jamais l'implémentation.

## Décisions structurantes

**Montants en centimes entiers.** Aucun flottant n'entre dans un calcul
d'argent. `intdiv` partout, arrondi plancher, toujours en faveur du budget.

**Rémunération au CPM.** `rate_per_1k_cents` = centimes pour 1000 vues. Un taux
« par vue » imposerait des fractions de centime.

**Grand livre append-only.** `campaign_budget_transactions` est la source de
vérité comptable ; `campaigns.spent_cents` n'est qu'un cache recalculable.
C'est ce qui rend possibles l'audit, l'annulation d'un clip frauduleux et
l'idempotence. `php artisan budget:audit` vérifie que les deux concordent — à
planifier quotidiennement en production.

**Une seule table `users`.** Administrateurs, modérateurs et clippeurs
partagent la table, distingués par `role`. Deux guards imposeraient des clés
étrangères polymorphes partout pour zéro gain.

**Budget consommé au crédit des vues, pas au paiement.** Un payout PayPal ne
touche jamais `spent_cents` : il déplace de l'argent déjà gagné vers le
clippeur. Un payout échoué rend le solde au clippeur, pas au budget.

## Le moteur, en une page

`DatabaseCampaignBudgetService::creditViews()` garantit quatre invariants :

1. `spent_cents` n'excède jamais `budget_total_cents`, quel que soit le nombre
   de crédits concurrents ;
2. `SUM(ledger.amount_cents) === campaigns.spent_cents` ;
3. une même clé d'idempotence ne débite qu'une fois ;
4. aucun crédit négatif quand une plateforme révise ses compteurs à la baisse.

Mécanique : `DB::transaction` avec reprise sur deadlock, `lockForUpdate()` dans
un ordre constant **campagne → clip**, plafonnement en cascade
`min(brut, reliquat, plafond clip, plafond clippeur)`, puis recalcul des vues
payées **depuis le montant plafonné** — sans quoi les vues excédentaires d'un
clip plafonné seraient marquées comme payées et perdues.

Aucun appel réseau ni job dans la transaction : un verrou de campagne tenu
pendant un appel PayPal bloquerait tous les autres crédits.

## Espace clippeur

### Identité visuelle

Bleu nuit pour le socle et les actions : on manipule de l'argent, l'interface
doit inspirer la fiabilité avant l'énergie. **La couleur porte le sens métier,
jamais la décoration** — vert pour les gains acquis, ambre pour ce qui est en
attente ou demande attention, rouge pour ce qui est perdu. Ce code est constant
d'un écran à l'autre : un montant vert est toujours de l'argent qui vous
appartient.

Tokens dans `tailwind.config.js`, classes composées dans `resources/css/app.css`
(`card`, `btn-primary`, `chip-ok`, `alert-warn`…). Bricolage Grotesque pour les
titres et les montants, Figtree pour le texte courant, chiffres en `tabular`
partout où ils s'alignent en colonne.

Connexion sur `/`, espace connecté sur `/dashboard` et `/campagnes`.
Authentification par **Laravel Breeze préset Blade** — le préset Livewire épingle
Livewire 3 alors que Filament 5 exige Livewire 4 ; les parties interactives
(catalogue, adhésion, soumission) sont donc des composants Livewire 4 écrits à
la main, dans `app/Livewire/`.

**Une seule table `users`, un seul guard.** Le rôle décide de la destination.

Trois middlewares gardent l'espace :

| Middleware | Rôle |
|---|---|
| `not.banned` | Déconnecte un compte suspendu à sa requête suivante, sans attendre l'expiration de sa session |
| `role:clipper` | Renvoie tout autre profil vers son propre espace |
| `profile.completed` | Impose pseudo, pays et adresse PayPal avant de participer |

Le profil est vérifié à chaque requête, pas au seul moment du retrait :
découvrir qu'il manque une adresse PayPal après avoir généré 200 000 vues est
la meilleure façon de perdre un clippeur.

### Catalogue et participation

Le catalogue lit le reliquat via `CampaignBudgetService::remaining()`, jamais par
une requête sur `campaigns.spent_cents` : la valeur affichée au clippeur est
exactement celle du back-office. Une campagne épuisée reste consultable, grisée
et non rejoignable.

Les plafonds anti-abus sont affichés d'emblée sur la fiche. Les découvrir après
coup, quand les gains cessent de monter, fait croire à un bug.

`ClipUrlParser` normalise les liens TikTok, YouTube et Instagram vers un
`external_post_id` canonique : deux URLs du même post — avec ou sans paramètres
de suivi, `youtu.be` ou `youtube.com/watch` — produisent le même identifiant,
sans quoi la contrainte d'unicité ne protégerait pas des doublons.

Un clip soumis naît toujours en `pending_review`, quelle que soit sa conformité :
un hashtag correct ne dit rien du respect réel du brief.

### Comptes réseaux et synchronisation

Tout ce qui dépend d'une API externe passe par le contrat `SocialProvider`.
Tant qu'une plateforme n'a pas ses identifiants d'application,
`SocialProviderManager` bascule sur `FakeSocialProvider` **hors production** :
la liaison de compte, la conformité, la synchronisation, le crédit du budget et
les gains fonctionnent de bout en bout sur des données simulées. En production,
l'absence de clés est une erreur, pas un mode dégradé silencieux.

Les vues simulées suivent une courbe déterministe — même publication, même
instant, même valeur — sinon les tests seraient instables et les compteurs
oscilleraient sans raison.

```bash
php artisan clips:sync                    # relève les vues et fait créditer
php artisan clips:sync --platform=tiktok  # une seule plateforme
php artisan social:refresh-tokens         # prolonge les jetons avant expiration
php artisan schedule:work                 # planificateur en local
```

**Économiser le quota** — batch par plateforme (50 identifiants pour une unité
chez YouTube), cadence dégressive (toutes les 3 h la première semaine, une fois
par jour ensuite, arrêt à J+30), et rien pour les clips qui ne rapportent plus.
Chaque passage est journalisé dans `social_sync_runs` : sans ça, un dépassement
de quota se diagnostique à l'aveugle.

Un compte marqué `needs_reconnect` est sauté : l'interroger ne rapporterait que
des 401 et consommerait le quota des comptes valides. Le clippeur voit un
bandeau d'alerte tant qu'il n'a pas reconnecté — c'est la panne la plus
silencieuse du système.

### Conformité au brief

`ClipComplianceChecker` confronte la publication aux exigences de la campagne au
premier relevé — c'est le moment où la légende et la durée réelles sont enfin
connues. Il produit **un rapport, jamais une décision** : un clip conforme reste
en attente de modération, un clip non conforme arrive devant le modérateur avec
ses motifs.

## Espace artiste

Un artiste peut avoir son propre compte : `artists.user_id`, nullable et unique.
Nullable parce qu'une fiche créée par l'admin n'a pas forcément de compte,
unique parce que « de quel artiste voit-il les statistiques ? » doit avoir une
réponse unique.

**Consultation seule.** L'artiste suit ses campagnes ; il ne les crée pas, ne
touche ni au budget ni à la modération. Il voit : budget engagé, dépensé,
restant, vues générées, **coût réel pour 1000 vues** — le seul indicateur de
rendement qui ait du sens, à comparer au CPM annoncé — le détail par campagne,
les clips classés par vues et la répartition par plateforme.

**Ce qu'il ne voit jamais** : les campagnes des autres artistes (404), ni
l'identité civile, l'e-mail ou l'adresse PayPal des clippeurs. Seul leur pseudo
apparaît. Deux tests verrouillent ces deux points.

Une fiche créée depuis l'inscription publique naît **inactive** : sans
validation d'un administrateur, n'importe qui apparaîtrait au catalogue sous le
nom qu'il veut. Le badge de navigation du back-office compte les fiches en
attente, sinon l'artiste attendrait une validation que personne ne sait devoir
faire.

### Aiguillage par rôle

`UserRole::homeRoute()` décide où atterrit chacun ; le middleware `role:` renvoie
un profil égaré vers son propre espace plutôt que de lui opposer un 403 sans
issue. `isStaff()` est une **liste blanche explicite** et non « tout sauf
clippeur » : ajouter un rôle ne doit pas lui ouvrir le back-office et les
paiements par simple oubli.

L'inscription publique ne peut créer qu'un clippeur ou un artiste — les rôles du
back-office ne se donnent pas par formulaire, même en trafiquant la requête.

## Modération

`ClipModerationService` porte les décisions ; il ne touche jamais aux compteurs
d'argent lui-même, il délègue à `CampaignBudgetService`.

| Action | Effet |
|---|---|
| Valider un clip | Le clip devient rémunérable |
| Refuser un clip | Réservé aux clips qui n'ont rien coûté |
| **Invalider** | Rend le budget à la campagne, qui peut ressortir de « Épuisée » |
| **Bannir un clippeur** | Gèle ses retraits en attente, bannit ses participations, invalide ses clips en option |

Toute décision est consignée dans `moderation_logs` avec son auteur, son motif
et son horodatage : invalider un clip revient à reprendre de l'argent à
quelqu'un, et un litige ne doit pas se réduire à la parole de l'admin contre
celle du clippeur.

`SuspiciousViewsDetector` remonte les courbes de vues improbables — bond de
×5 et +10 000 vues en moins de 6 h, démarrage à plus de 50 000 vues dans
l'heure, plus de 100 vues par abonné. Les seuils sont dans
[`config/clipping.php`](config/clipping.php). **Aucun seuil ne sanctionne
automatiquement** : ils trient la file de modération, la décision reste humaine.

## Paiements

```bash
php artisan payouts:send --dry-run   # affiche le lot sans rien envoyer
php artisan payouts:send             # crée le lot PayPal Payouts
php artisan payouts:sync             # réconcilie les lots en vol
php artisan accounting:export depenses --from=2026-01-01
php artisan accounting:export versements
```

Le solde d'un clippeur est **toujours calculé** (gains − retraits demandés ou
versés), jamais stocké : un solde dénormalisé finit par diverger du grand
livre. Il est vérifié sous `lockForUpdate()`, même discipline que le budget de
campagne — deux demandes simultanées ne doivent pas retirer deux fois.

L'ordre des opérations à l'envoi est ce qui protège l'argent : les retraits
passent en « en cours » **avant** l'appel réseau. Si l'appel se perd, on sait
quoi réconcilier ; l'inverse produirait des virements dont on ignore
l'existence. Un lot introuvable chez PayPal remet les retraits en file.

Les webhooks arrivent sur `POST /webhooks/paypal`, hors CSRF, authentifiés par
signature (`PAYPAL_WEBHOOK_ID`). Le traitement est idempotent : PayPal envoie
en double, dans le désordre, ou pas du tout — `payouts:sync` est le filet.

Configuration : `PAYPAL_MODE`, `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET`,
`PAYPAL_WEBHOOK_ID` dans `.env`. Sandbox par défaut.

## Reporting

Tableau de bord `/admin` : budget engagé, consommé, **dû aux clippeurs**
(consommé − versé, le chiffre à provisionner), vues et CPM réel ; courbe de
consommation sur 30 jours ; dépenses par artiste ; top clippeurs avec leur taux
d'invalidation.

Toutes les agrégations lisent le grand livre plutôt que les compteurs
dénormalisés : les chiffres sont reproductibles et auditables.

## Tests

```bash
php artisan test
```

Trois contraintes expliquent la mécanique inhabituelle de
`tests/Feature/Budget/BudgetConcurrencyTest.php` :

- **MySQL obligatoire.** SQLite n'a pas de verrou de ligne : `lockForUpdate()`
  y est un no-op silencieux, et un moteur de budget testé sur SQLite passerait
  tous les tests de concurrence sans rien garantir en production.
- **`DatabaseTruncation`, pas `RefreshDatabase`.** La transaction englobante de
  `RefreshDatabase` rendrait les données invisibles aux autres processus.
- **De vrais processus système.** `pcntl_fork` n'existe pas sous Windows, et
  des appels successifs dans un même processus ne se croisent jamais. Les tests
  lancent N commandes `budget:credit-clip` en parallèle, toutes bloquées sur un
  fichier-barrière levé au dernier moment pour maximiser la collision.

Scénarios couverts : 20 crédits simultanés sur un budget qui n'en autorise que
10, plafonnement au reliquat exact, même clé d'idempotence tirée cinq fois en
parallèle, et un fuzz de 15 crédits aléatoires vérifiant la cohérence du grand
livre.

Le reste de la suite couvre la machine à états, la modération, les paiements
(PayPal simulé via `Http::fake`), les exports comptables, le reporting, et un
test de fumée qui charge chaque page du back-office — les widgets sont des vues
Blade, sans quoi une erreur de template ne se verrait qu'à l'œil nu.

## Périmètre restant

Le produit tourne de bout en bout. Ce qui reste tient à des dépendances
externes, pas à du code manquant :

- **Identifiants d'application** TikTok, Google et Meta. Les revues TikTok et
  Meta prennent plusieurs jours ouvrés : c'est le chemin critique du projet, à
  lancer indépendamment du développement.
- **Validation des trois clients d'API sur les vraies plateformes.**
  `YouTubeProvider`, `TikTokProvider` et `InstagramProvider` sont écrits avec
  leurs endpoints réels mais n'ont jamais reçu de réponse authentique. Les
  premiers points à revérifier sont marqués « À VÉRIFIER » dans le code.
- **SMTP de production.** Mailpit capture les e-mails en développement ; il
  faudra un vrai fournisseur d'envoi avant que de vrais clippeurs s'inscrivent.
- **Publication depuis la plateforme** (Content Posting API de TikTok). Hors
  périmètre à ce jour : le modèle est que le clippeur publie lui-même, puis
  colle le lien.
