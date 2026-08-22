# Clip Adem

Plateforme de clipping : un administrateur crée des campagnes de promotion pour
des artistes avec un budget total et un taux de rémunération par plateforme.
Des clippeurs publient des clips, et sont payés selon les vues générées, jusqu'à
épuisement du budget — premier arrivé, premier servi.

Le dépôt est partagé entre deux périmètres :

| Périmètre | Responsable | Contenu |
|---|---|---|
| Espace admin + moteur de campagne / budget | Ilias | Ce qui est décrit ci-dessous |
| Espace clippeur + intégrations réseaux sociaux | Anas | `social_accounts`, `campaign_participations`, `clips`, `clip_view_snapshots`, `payouts` |

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

Panel admin sur `/admin`. Comptes de démonstration (mot de passe `password`) :

- `admin@clip-adem.test` — super-administrateur
- `moderateur@clip-adem.test` — modérateur

La double authentification TOTP est obligatoire : au premier accès, le panel
impose de scanner un QR code avant de laisser entrer.

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

## Reste à faire

- Modération des clippeurs et des clips (bannissement, invalidation en masse)
- Paiements PayPal Payouts + export comptable CSV
- Reporting : dépenses par artiste, top clippeurs, courbes de consommation
