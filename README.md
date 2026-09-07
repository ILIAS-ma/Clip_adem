# Clip Adem

Plateforme de clipping : un administrateur crée des campagnes de promotion pour
des créateurs avec un budget total et un taux de rémunération par plateforme.
Des clippeurs publient des clips, et sont payés selon les vues générées, jusqu'à
épuisement du budget — premier arrivé, premier servi.

Le dépôt est partagé entre deux périmètres :

| Périmètre | Responsable | État |
|---|---|---|
| Espace admin + moteur de campagne / budget | Ilias | Livré |
| Espace clippeur complet : auth, catalogue, comptes, clips, revenus | Ilias | Livré |
| Espace créateur : suivi des campagnes et du rendement | Ilias | Livré |
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
`/createur` pour un créateur, `/admin` pour le staff. La page de présentation du
fonctionnement reste sur `/presentation`.

### Comptes de démonstration

```bash
php artisan demo:accounts
```

Quatre comptes remplis, un par rôle, mot de passe `password`. Chacun arrive sur
un espace déjà peuplé : un tableau de bord vide ne montre que des états
d'attente, et c'est précisément ce qu'on ne cherche pas à voir en découvrant le
produit. La commande est idempotente — la relancer met les comptes à jour sans
les dupliquer.

| Compte | Rôle | Ce qu'il voit |
|---|---|---|
| `admin@clip.test` | super-administrateur | Campagnes, modération, retraits, reporting |
| `moderateur@clip.test` | modérateur | Idem sans les paiements |
| `clippeur@clip.test` | clippeur | Niveau Expert, deux clips, un retrait en attente |
| `createur@clip.test` | créateur | Sa campagne, ses vues, son coût réel aux 1000 vues |

Le seed crée en plus `lina@clippeur.test`, `karim@clippeur.test` (un clip aux
vues suspectes) et `nayra@createur.test`.

> Les sessions partagent le même cookie : pour comparer deux rôles côte à côte,
> ouvrez-les dans des fenêtres de navigation privée distinctes.

### Passages obligés, suspendables

Trois contrôles peuvent être suspendus pour parcourir l'interface sans obstacle
pendant le développement :

| Variable `.env` | Effet quand `false` |
|---|---|
| `REQUIRE_EMAIL_VERIFICATION` | L'e-mail n'a plus besoin d'être confirmé |
| `REQUIRE_COMPLETE_PROFILE` | Pseudo, pays et moyen de paiement ne bloquent plus |
| `REQUIRE_ADMIN_2FA` | Le panel n'impose plus de scanner un QR code |
| `REQUIRE_CREATOR_VALIDATION` | Une fiche créateur est active dès sa création |
| `REQUIRE_FUNDED_CAMPAIGNS` | Une campagne s'active sans encaissement enregistré |

Aucun code n'est commenté ni supprimé : les contrôles restent en place, et la
suite de tests **les force à `true`** pour continuer de les vérifier. Un bandeau
orange s'affiche sur toutes les pages tant qu'un contrôle est suspendu — sans
lui, un contrôle désactivé « le temps de voir l'interface » finit en production.

À rétablir avant toute mise en ligne : sans vérification d'e-mail, une adresse
jetable rend le bannissement inopérant ; sans 2FA, un compte admin compromis
donne accès aux paiements ; sans validation des fiches, n'importe qui apparaît
au catalogue sous le nom de scène qu'il veut.

Quand la vérification d'e-mail est suspendue, l'inscription **n'envoie plus** le
message de confirmation : il ne servirait à rien, et son envoi ferait échouer
toute l'inscription si le serveur de mail est absent. Le compte reste non
vérifié — rétablir le contrôle lui redemandera de confirmer.

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

Reprise du logo : **noir profond et vert lime** (`#93CE2E`), interface sombre de
bout en bout, back-office compris.

**Une seule couleur d'accent, et elle porte deux choses à la fois** — la marque
et l'argent acquis. Sur cette palette, un montant en lime veut donc toujours
dire « ce gain vous appartient ». L'ambre signale l'attente, le rouge la perte :
trois signaux, jamais de couleur décorative. Ce code est constant d'un écran à
l'autre.

Les neutres sont très légèrement tirés vers le vert : posés à côté du lime, des
gris parfaitement neutres paraissent sales. L'échelle `ink` garde sa convention
habituelle — 50 le plus clair, 950 le plus sombre — même si l'interface est
sombre : c'est ce qu'un développeur attend en lisant `text-ink-50`.

Tokens dans `tailwind.config.js`, classes composées dans `resources/css/app.css`
(`card`, `btn-primary`, `chip-ok`, `alert-warn`…). Bricolage Grotesque pour les
titres et les montants, Figtree pour le texte courant, chiffres en `tabular`
partout où ils s'alignent en colonne.

**Le logo** vit dans `public/images/logo.png` — repris par la marque du site, le
favicon et le back-office. La source pleine résolution est conservée à côté sous
`logo-source.png` ; celle qui est servie est réduite à 256 px, le logo ne
dépassant jamais 40 px à l'écran (1,3 Mo → 52 Ko).

Le logo portant déjà son mot-symbole, le nom de l'application ne s'affiche pas à
côté : il ne réapparaît qu'en l'absence de fichier, où une onde sonore lime sert
de repli — aucun écran ne dépend d'un fichier manquant.

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

### Brancher les vraies plateformes

TikTok publie **deux** guides Login Kit, aux règles opposées. Clip Adem est une
application web serveur : c'est le guide **Web** qui s'applique, et il faut
enregistrer une application de type Web.

| | Web (le nôtre) | Desktop |
|---|---|---|
| Adresse de retour | `https` obligatoire | `localhost` / `127.0.0.1`, `http` accepté |
| PKCE | non utilisé | obligatoire, SHA256 en **hexadécimal** |
| Utilisable en production | oui | non — l'adresse de retour ne peut être que locale |

Enregistrer une application Desktop pour éviter un tunnel en développement est
une impasse : la même application ne pourra jamais servir en ligne.

Pour TikTok :

1. Application **Web** sur developers.tiktok.com, produit Login Kit, portées
   `user.info.basic` et `video.list` (revue d'application, plusieurs jours).
2. Adresse de retour enregistrée : `https://votre-domaine/oauth/tiktok/callback`
   — absolue, sans paramètre, sans fragment.
3. `TIKTOK_CLIENT_KEY` et `TIKTOK_CLIENT_SECRET` dans `.env`.

En développement, il faut donc un tunnel HTTPS. Deux réglages, sans lesquels la
plateforme répond « invalid redirect_uri » sans dire pourquoi :

```
TRUSTED_PROXIES=*
TIKTOK_REDIRECT_URI=https://votre-tunnel/oauth/tiktok/callback
```

Le premier parce que le tunnel termine TLS en amont : sans lui Laravel se croit
en HTTP et fabrique une adresse `http://`. Le second parce que l'adresse est
comparée **caractère par caractère** avec celle enregistrée, et que la déduire
de l'hôte de la requête est fragile. Laissés vides, l'adresse est déduite de la
route — ce qui suffit tant qu'on reste sur le fournisseur simulé.

**Permissions accordées.** Les plateformes laissent refuser une permission tout
en accordant les autres. Un compte lié sans l'accès aux statistiques se comporte
normalement — il apparaît dans la liste, on peut rejoindre une campagne et
publier — puis ne remonte jamais une vue : le clippeur découvre au bout d'une
semaine qu'il ne sera pas payé. `SocialAccountLinker` refuse donc la liaison en
nommant la permission manquante. Le contrôle ne s'applique que si le
fournisseur renvoie la liste : un refus à tort empêcherait quelqu'un de gagner
sa vie, alors qu'un contrôle manqué revient au comportement d'avant.

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

### Pourquoi les vues ne sont pas « en direct »

La question revient toujours, et la réponse est structurelle : **aucune des
trois plateformes ne pousse son compteur de vues.** Il n'existe pas de webhook
« cette vidéo vient de gagner mille vues » — il faut interroger. Tout ce qui
suit en découle.

Pire : le compteur que TikTok renvoie sur un compte autorisé n'est lui-même pas
temps réel. Interroger toutes les minutes rendrait donc le même nombre, en
brûlant le quota. Un relevé horaire pour un clip récent est, en pratique,
aussi frais que ce que la plateforme sait.

D'où la cadence dégressive, en trois paliers :

| Âge du clip | Relevé | Pourquoi |
|---|---|---|
| moins de 48 h | toutes les **30 min** | L'essentiel des vues se fait là, et c'est là que le clippeur regarde son solde |
| 2 à 7 jours | toutes les 3 h | La courbe s'aplatit |
| 7 à 30 jours | une fois par jour | Elle ne bouge presque plus |
| au-delà | plus rien | Interroger consommerait le quota des clips qui rapportent |

Le palier chaud ne coûte presque rien : TikTok annonce 600 appels par minute et
nous groupons 20 vidéos par appel. À 500 clips suivis, un passage complet fait
25 appels — **4 % de la limite**. La contrainte réelle est le quota *quotidien*,
que TikTok ne documente pas clairement : `SocialSyncRun` le journalise à chaque
passage, à mesurer en sandbox avant de resserrer davantage.

Les clips les plus récents sont servis en premier. Si le quota s'épuise en cours
de passage, ce sont ceux dont les vues bougent qui doivent avoir été relevés —
sans cet ordre, un mois d'archives figées pourrait tout consommer avant que la
publication d'hier soit lue une seule fois.

**Le bouton « Actualiser les vues »** de la page d'un clip comble l'écart
ressenti : entre deux passages automatiques, c'est le seul recours d'un clippeur
qui veut voir son solde bouger. Un délai de garde de 15 minutes l'empêche de
devenir une attaque sur notre propre quota — sans lui, un clic répété brûlerait
les appels dont les autres ont besoin. Le relevé manuel passe exactement par le
même chemin que le relevé automatique : conformité, instantané, crédit du
budget. Deux chemins d'écriture pour la même chose finiraient par diverger, et
c'est de l'argent qui passe là.

### Conformité au brief

`ClipComplianceChecker` confronte la publication aux exigences de la campagne au
premier relevé — c'est le moment où la légende et la durée réelles sont enfin
connues. Il produit **un rapport, jamais une décision** : un clip conforme reste
en attente de modération, un clip non conforme arrive devant le modérateur avec
ses motifs.

**Une seule exception, et elle bloque le paiement : la propriété.** Les autres
contrôles relèvent du jugement — un hashtag oublié se discute, une durée limite
aussi — et laissent donc le crédit suivre son cours, quitte à être repris par
une invalidation ; bloquer sur un hashtag priverait de leur argent des clippeurs
de bonne foi. La propriété est factuelle et binaire : soit la publication vient
du compte lié, soit elle vient d'ailleurs. Se tromper signifie payer quelqu'un
pour la vidéo virale d'un inconnu.

La fenêtre a existé : un clip validé par un modérateur *avant* son premier
relevé était crédité deux lignes après le contrôle, sans que personne consulte
le résultat. Le crédit est désormais refusé, les vues restent enregistrées pour
que la modération voie l'ampleur de ce qui a failli partir, et une ligne de
journal est écrite — une seule, le relevé repassant toutes les trois heures.

### Matière première du brief

Un brief textuel ne suffit pas : un clippeur a besoin d'entendre le son imposé
et de voir des exemples avant de tourner. `campaign_assets` porte les pièces
jointes d'une campagne — **son, vidéo, image, document** — déposées comme
fichiers ou pointées par un lien externe.

Une table plutôt que deux colonnes d'URL : le nombre de pièces varie, chacune
porte son propre type, sa consigne d'usage (« caler le drop à 0:12 ») et son
caractère imposé ou non. Un lien unique « pack visuel » ne dit ni ce que c'est,
ni s'il faut absolument s'en servir.

Cinq types, et `AssetKind` en est la source unique — extensions acceptées,
traduction en types MIME, poids maximum :

| Type | Formats | Poids max |
|---|---|---|
| Son | mp3, wav, m4a, aac, ogg, flac, aiff… | 50 Mo |
| Vidéo | mp4, mov, webm, m4v, avi, mkv, mpeg… | 500 Mo |
| Image | jpg, png, webp, gif, avif, heic, tiff… | 25 Mo |
| Document | pdf, txt, md, docx, xlsx, pptx, csv, odt, srt… | 50 Mo |
| Archive | zip, rar, 7z, tar, gz | 500 Mo |

Le SVG est la seule exclusion délibérée : servi depuis notre propre domaine, il
peut embarquer du script et s'exécuter dans le contexte du site. Un test le
verrouille, pour qu'on ne le réintroduise pas par distraction.

**Le plafond de Livewire est le vrai goulot.** Il est à 12 Mo par défaut, et
s'applique *avant* la validation de Filament : un rush vidéo était refusé par un
message qui n'expliquait rien. `config/livewire.php` le porte à 512 Mo, et un
test vérifie qu'aucun type déclaré ne le dépasse. PHP doit suivre —
`upload_max_filesize` et `post_max_size` au moins aussi hauts.

Les pièces se gèrent depuis le formulaire de campagne **et** depuis l'onglet
« Matière première » de la page d'édition. Le second existe parce qu'ajouter un
rush trois jours après le lancement ne devrait pas obliger à re-soumettre un
formulaire qui porte le budget et les taux : une erreur de manipulation y
coûterait bien plus cher qu'une pièce jointe.

- **Fichier OU lien, jamais les deux.** Un fichier déposé efface le lien : deux
  sources pour la même pièce, c'est deux vérités sur ce qu'il faut utiliser.
- **Poids et type relus depuis le disque**, pas depuis le formulaire — le
  navigateur peut se tromper, le disque non.
- **Aperçu sur place** pour ce que le navigateur sait lire. Envoyer quelqu'un
  télécharger 80 Mo pour découvrir que ce n'était pas le bon son, c'est le
  perdre.
- Les extensions et le poids maximum acceptés sont dérivés du type choisi
  (`AssetKind`), source unique côté formulaire comme côté validation.

## Espace créateur

Un créateur peut avoir son propre compte : `creators.user_id`, nullable et unique.
Nullable parce qu'une fiche créée par l'admin n'a pas forcément de compte,
unique parce que « de quel créateur voit-il les statistiques ? » doit avoir une
réponse unique.

**Consultation seule.** Le créateur suit ses campagnes ; il ne les crée pas, ne
touche ni au budget ni à la modération. Il voit : budget engagé, dépensé,
restant, vues générées, **coût réel pour 1000 vues** — le seul indicateur de
rendement qui ait du sens, à comparer au CPM annoncé — le détail par campagne,
les clips classés par vues et la répartition par plateforme.

**Ce qu'il ne voit jamais** : les campagnes des autres créateurs (404), ni
l'identité civile, l'e-mail ou l'adresse PayPal des clippeurs. Seul leur pseudo
apparaît. Deux tests verrouillent ces deux points.

Une fiche créée depuis l'inscription publique naît **inactive** : sans
validation d'un administrateur, n'importe qui apparaîtrait au catalogue sous le
nom qu'il veut. Le badge de navigation du back-office compte les fiches en
attente, sinon le créateur attendrait une validation que personne ne sait devoir
faire. Ce contrôle fait partie des passages obligés suspendables
(`REQUIRE_CREATOR_VALIDATION`).

### Des chiffres lisibles par un créateur

`CreatorStatsService` répond aux trois seules questions qu'un créateur pose :
combien de vues, combien ça m'a coûté, est-ce que ça marche. Le tableau de bord
s'ouvre sur **une phrase** puis trois chiffres — vues, dépensé, coût pour 1000
vues — chacun accompagné de ce qu'il veut dire, suivis de deux graphiques sur 30
jours et des clips qui ont réellement porté la campagne.

Tout est lu depuis `campaign_budget_transactions`, jamais depuis
`campaigns.spent_cents` : le grand livre est la seule table qui redescend d'elle-
même quand des vues achetées sont invalidées. Afficher le cache montrerait au
créateur une dépense que la plateforme a déjà annulée.

Les clips sont classés sur les **vues rémunérées**, pas sur le compteur brut : un
clip dont les vues ont été refusées n'a rien apporté.

### Aiguillage par rôle

`UserRole::homeRoute()` décide où atterrit chacun ; le middleware `role:` renvoie
un profil égaré vers son propre espace plutôt que de lui opposer un 403 sans
issue. `isStaff()` est une **liste blanche explicite** et non « tout sauf
clippeur » : ajouter un rôle ne doit pas lui ouvrir le back-office et les
paiements par simple oubli.

L'inscription publique ne peut créer qu'un clippeur ou un créateur — les rôles du
back-office ne se donnent pas par formulaire, même en trafiquant la requête.

## Progression des clippeurs

Cinq niveaux — Débutant, Confirmé, Expert, Élite, Légende — calculés depuis
`clips.paid_views`, **jamais depuis `views_total`**. C'est la décision qui tient
tout le reste : seul `paid_views` compte ce que le moteur de budget a réellement
crédité, et `reverseClip()` le remet à zéro. Un niveau ne peut donc pas
récompenser les vues que le détecteur de fraude essaie d'attraper, ni servir à
les blanchir.

```
XP = vues rémunérées
   + 2 000 par clip validé
   + 5 000 par campagne distincte
   − 20 000 par clip invalidé
```

**Le niveau est acquis, les avantages se maintiennent.** Le niveau ne redescend
jamais : c'est une reconnaissance. Les avantages exigent un volume de vues
rémunérées sur 90 jours glissants — sinon un clippeur inactif depuis un an
garderait un accès prioritaire au détriment de ceux qui travaillent.

| Niveau | XP | Accès anticipé | Plafond par clip |
|---|---|---|---|
| Débutant | 0 | — | ×1 |
| Confirmé | 50 000 | — | ×1,5 |
| Expert | 250 000 | 12 h | ×1,75 |
| Élite | 1 000 000 | 24 h | ×2 |
| Légende | 5 000 000 | 24 h | ×2 |

**Accès anticipé** : une campagne programmée s'ouvre plus tôt aux niveaux
élevés. C'est l'avantage le plus fort de la plateforme — le budget partant au
premier arrivé, l'antériorité est la vraie monnaie — et il ne coûte rien au
budget.

**Plafond par clip relevé** : le niveau ne déplace que la répartition. Le budget
total de la campagne reste le plafond absolu, et un test le prouve. Le CPM, lui,
n'est jamais majoré : un admin qui fixe 0,50 € / 1000 sur 1 500 € doit savoir
combien de vues il achète.

Rien n'est stocké : tout se recalcule depuis les clips et le grand livre. Un
compteur dénormalisé finirait par diverger, et il donnerait alors des avantages
à quelqu'un qui ne les a pas gagnés. Un compte banni repart de zéro, sans quoi
le niveau deviendrait un actif qu'on revend avec le compte.

Seuils ajustables dans `config/clipping.php` — ils sont provisoires, à recaler
sur les données réelles.

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

### Moyen de paiement : PayPal ou virement bancaire

Une page, un formulaire : « où voulez-vous être payé ». Le moyen de paiement est
sorti du profil général parce que c'est la seule information qu'un clippeur
revient modifier, et qu'elle ne doit pas se perdre au milieu du pseudo et du
pays. Le même bloc sert à la fin d'inscription et à la page dédiée — deux
formulaires finiraient par ne plus accepter les mêmes IBAN.

- **L'IBAN est chiffré** au niveau du modèle : une donnée bancaire ne doit pas
  sortir en clair d'un dump de base. Seuls les quatre derniers chiffres sont
  conservés en clair, pour afficher `•••• 0189` et rapprocher un virement sans
  jamais déchiffrer quoi que ce soit.
- **Vérification structurelle** (longueur par pays + clé mod 97) : elle attrape
  la faute de frappe, pas le compte fermé. C'est déjà l'essentiel — un virement
  rejeté coûte des frais et une semaine.
- **Changer de mode efface l'autre.** Garder un IBAN dormant sur un compte passé
  à PayPal, c'est conserver une donnée bancaire dont plus personne n'a l'usage.
- **La destination est figée sur le retrait**, masquée, au moment de la demande.
  Un changement de RIB ne réécrit pas l'histoire d'un virement déjà parti.

Les virements bancaires ne partent **jamais** dans un lot PayPal. Le
back-office propose un **fichier des virements** (CSV, IBAN en clair, réservé au
super-administrateur) à ouvrir à côté de l'interface bancaire, puis une action
« Virement effectué » par ligne, avec la référence du relevé. Aucune API ne nous
dit qu'un SEPA est parti : quelqu'un doit le dire, et la trace de modération dit
qui et quand. Symétriquement, un retrait PayPal **ne peut pas** être pointé à la
main — sinon un administrateur pourrait déclarer versé un retrait que PayPal n'a
jamais envoyé, et le solde du clippeur disparaîtrait.

## Connexion Google

Écrite à la main, pas via Socialite : celui-ci exige Guzzle 7 quand le projet
tourne sur Guzzle 8, et descendre une bibliothèque HTTP centrale pour une page
de connexion serait un mauvais échange. Le projet parle déjà OAuth avec TikTok,
YouTube et Instagram — c'est le même patron, et il reste sous notre contrôle.

L'identifiant stocké est le `sub` de Google, **jamais l'e-mail** : une adresse
peut changer de main, le `sub` désigne le même compte pour toujours.

La règle qui compte tient en une phrase : **on ne rattache jamais un compte
existant sur une adresse que Google n'a pas vérifiée.** Sans elle, il suffirait
de créer un compte Google déclarant l'adresse d'un administrateur pour prendre
sa place. Trois tests l'entourent.

Le profil est relu par l'API `userinfo` plutôt que décodé depuis l'`id_token` :
vérifier une signature JWT à la main est exactement le genre de code qu'on écrit
une fois, mal, et qui laisse passer un jeton forgé.

Configuration : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, et l'URI de
redirection `https://votre-domaine/auth/google/callback` déclarée à l'identique
dans la console Google Cloud. Sans clé, le bouton ne s'affiche pas — un bouton
qui mène à une erreur est pire que pas de bouton.

## Parrainage

**La commission ne sort jamais du budget d'une campagne.** Elle est payée par
la plateforme sur sa marge. Sinon le créateur financerait la croissance de la
plateforme sans le savoir, et le budget qu'il a provisionné ne servirait plus
entièrement à ses vues — ce que le contrôle d'encaissement existe justement
pour garantir. Le filleul n'est pas amputé non plus : il touche exactement ce
que ses vues valent, la commission s'ajoute par-dessus. Deux tests gardent
cette règle, parce que c'est le genre d'invariant qu'une optimisation
« évidente » casse six mois plus tard.

`referral_commissions` suit la même forme que le grand livre : ajout seul,
signé, idempotent. Une transaction budget ne donne qu'une commission — index
unique — et l'invalidation d'un clip écrit des lignes négatives plutôt que de
supprimer les anciennes : le parrain doit pouvoir comprendre pourquoi son total
a baissé.

Les commissions entrent dans le solde retirable, sinon elles s'afficheraient
sans jamais pouvoir être touchées.

Le code est attribué à la demande, pas à l'inscription, et son alphabet exclut
O/0 et I/1 : ces codes se recopient à la main depuis une story. Le parrain est
figé à l'inscription — le laisser changer permettrait de déplacer des
commissions déjà acquises.

Barème : `REFERRAL_RATE_BP`, en points de base (500 = 5 %).

## Message groupé

`/admin/send-broadcast`, réservé au super-administrateur : c'est le seul écran
qui parle à tous les utilisateurs à la fois, et un e-mail parti ne se rappelle
pas. Le nombre exact de destinataires s'affiche en gros au-dessus du formulaire
et se recalcule à chaque changement de filtre — c'est ce qui empêche de se
tromper de groupe.

L'envoi passe par `chunkById` et par la file : tout charger en mémoire tombe à
quelques milliers de comptes, et trois cents e-mails dans le cycle d'une requête
HTTP la font expirer bien avant la fin, sans qu'on sache qui a reçu quoi.

## L'argent qui entre

`campaigns.budget_total_cents` n'était qu'un nombre tapé au clavier : rien ne le
reliait à un encaissement réel. La plateforme pouvait donc devoir 5 000 € à des
clippeurs sans avoir reçu un centime — `budget:audit` vérifiait la cohérence
interne, jamais la solvabilité.

`campaign_fundings` est le pendant du grand livre des dépenses : en ajout seul,
signé, jamais modifié. Un remboursement au créateur est une ligne négative, pas
une suppression, parce que six mois plus tard il faut encore savoir ce qui a
transité.

Trois chiffres en découlent, et le troisième est le seul qui compte vraiment :

- `fundedCents()` — ce qui a été reçu ;
- `unfundedCents()` — ce qui reste à recevoir pour couvrir le budget engagé ;
- **`exposureCents()`** — ce qui a été dépensé au-delà de ce qui a été encaissé.
  Au-delà de zéro, l'argent promis aux clippeurs sort de la poche de la
  plateforme. La colonne « Encaissé » du back-office l'affiche en rouge.

**Une campagne ne s'active plus si son budget n'est pas couvert.** Le contrôle
rejoint les autres passages obligés suspendables (`REQUIRE_FUNDED_CAMPAIGNS`) —
mais le suspendre en production, c'est promettre de l'argent qu'on n'a pas.

La saisie des encaissements est réservée au super-administrateur : c'est ce qui
débloque l'activation, donc ce qui engage la plateforme à payer. Un modérateur
n'a pas à pouvoir déclarer qu'un virement est arrivé.

## Publication disparue

Publier, encaisser sur trois jours, effacer la vidéo : le vecteur de fraude le
moins coûteux contre la plateforme. Le relevé notait « rien à lire » et passait
au suivant, sans laisser de trace.

`clips.missing_since` et `clips.missing_checks` changent ça. Deux colonnes
plutôt qu'un booléen : la date dit depuis quand, le compteur dit combien de
relevés consécutifs l'ont manquée. **Un seul échec ne prouve rien** — une
publication passée en privé une heure, une API qui bafouille — et accuser
quelqu'un sur un hoquet coûte plus cher que d'attendre le relevé suivant. Au
deuxième, une ligne de modération est écrite, une seule fois, avec le montant
déjà crédité. Si la publication revient, l'alerte s'efface.

Rien n'est repris automatiquement : le budget consommé ne revient que par une
invalidation explicite d'un modérateur. Une disparition signale, elle ne juge
pas.

## Notifications

Un clippeur travaille et attend d'être payé ; il ne devrait pas avoir à ouvrir
le site pour savoir où il en est. Trois e-mails, et trois seulement :

| Quand | Pourquoi |
|---|---|
| Clip refusé ou invalidé | Sans le motif, la personne republiera à l'identique. Si le clip avait déjà été payé, l'e-mail dit que le solde baisse — le découvrir soi-même fait croire à une erreur. |
| Virement exécuté | Avec le délai bancaire, sinon « je n'ai rien reçu » arrive deux heures plus tard. |
| Versement rejeté | L'argent réapparaît dans le solde : sans explication, la lecture naturelle est « on ne m'a pas payé ». |

Pas d'e-mail pour un clip validé ni pour un état intermédiaire : une
notification par changement d'état finit en filtre anti-spam, ce qui coûterait
ensuite les trois qui comptent.

Les envois sont **mis en file et ne peuvent jamais faire échouer l'opération**
qui les déclenche : une invalidation qui rend le budget à la campagne ne
s'annule pas parce qu'un e-mail n'est pas parti. Un test le vérifie en faisant
exploser l'envoi.

La file exige un consommateur. `routes/console.php` planifie
`queue:work --stop-when-empty` chaque minute plutôt qu'un démon : sur un
hébergement mutualisé, il n'y a souvent aucun moyen de garder un processus
vivant, et une file qu'on croit traitée est pire que pas de file du tout. Avec
un vrai worker supervisé, cette ligne devient sans effet.

## Reporting

Tableau de bord `/admin` : budget engagé, consommé, **dû aux clippeurs**
(consommé − versé, le chiffre à provisionner), vues et CPM réel ; courbe de
consommation sur 30 jours ; dépenses par créateur ; top clippeurs avec leur taux
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

## Avant d'ouvrir au public

```bash
php artisan clip:preflight
```

Le développement se fait volontairement garde-fous baissés : e-mails capturés
en local, contrôles suspendus, fournisseurs simulés, comptes de démonstration à
mot de passe public. Aucun de ces réglages ne doit survivre à la mise en ligne,
et un `.env` recopié tel quel est la façon la plus banale de mettre un site
ouvert en danger.

La commande contrôle en une page l'environnement, les cinq passages obligés,
l'envoi réel des e-mails, les clés des trois plateformes, PayPal et la
signature de ses webhooks, le lien de stockage, la file d'attente, la
solvabilité des campagnes actives et les comptes `@clip.test` oubliés. Elle
rend un code de sortie non nul s'il reste un point bloquant : un script de
déploiement peut s'arrêter dessus.

Un point mérite d'être connu d'avance : **sans clés TikTok, YouTube et
Instagram, l'application lève une exception en production** — c'est délibéré,
`SocialProviderManager` refuse de basculer silencieusement sur le fournisseur
simulé hors développement. Ce serait pire : des vues inventées crédiseraient
de l'argent réel.

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
- **Statut réglementaire du flux d'argent.** Encaisser pour reverser à des
  tiers est réglementé, et une plateforme de mise en relation a des obligations
  déclaratives (DAC7) qui supposent des données d'identité que nous ne
  collectons pas. À faire trancher par un comptable ou un juriste **avant**
  l'ouverture : la réponse peut supprimer purement et simplement le stockage
  des IBAN, au profit d'un prestataire qui porte l'agrément.
- **Exécution automatique des virements SEPA.** Aujourd'hui l'administrateur
  télécharge le fichier des virements et les saisit en banque. Un fichier
  pain.001 déposé chez la banque supprimerait cette étape, mais suppose un
  contrat de télétransmission — décision commerciale avant décision technique.
- **Stockage des pièces jointes.** Le disque `public` local convient au
  développement ; des rushes vidéo en production appellent S3 et un CDN.
