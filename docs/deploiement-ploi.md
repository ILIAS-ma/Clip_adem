# Déploiement sur Ploi

Ce document couvre la mise en ligne. Ce qu'il faut vérifier *avant* d'ouvrir au
public — garde-fous, clés, comptes de démonstration — est déjà décrit dans le
README, section « Avant d'ouvrir au public », et contrôlé par `clip:preflight`.

## Le serveur

Ploi ne loue pas de machine : il configure celle qu'on lui donne. Un serveur
suffit pour tout (web, base, file d'attente, planificateur).

| | |
|---|---|
| Fournisseur | Hetzner CX22 — 2 vCPU, 4 Go, ~5 €/mois |
| Type Ploi | *Server* (nginx + PHP-FPM + MySQL sur la même machine) |
| PHP | **8.4** (le projet exige `^8.3`) |
| Base | MySQL 8 |
| Node | à installer depuis Ploi — les assets sont compilés sur le serveur |

4 Go de RAM ne sont pas du luxe : la compilation Vite au déploiement consomme
plus que l'application en fonctionnement, et c'est elle qui décide de la taille
minimale de la machine.

Extensions PHP : celles installées par Ploi suffisent. `intervention/image`
exige **GD**, présent par défaut — c'est lui qui recadre les avatars.

## Le site

Domaine, puis dépôt `ILIAS-ma/Clip_adem`, branche `main`. Activer le
certificat Let's Encrypt : sans HTTPS, les adresses de retour OAuth sont
refusées par TikTok comme par Google.

## Le script de déploiement

```bash
cd /home/ploi/VOTRE-DOMAINE

git pull origin main

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

npm ci
npm run build

php artisan migrate --force
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart

echo "" | sudo -S service php8.4-fpm reload

php artisan clip:preflight
```

Trois lignes méritent une explication.

**`storage:link`** — les pièces jointes des briefs passent par le disque
`public` (`CampaignAsset`). Sans ce lien symbolique, les fichiers sont bien
enregistrés mais **aucun n'est téléchargeable** : les clippeurs voient une
pièce jointe qui renvoie une 404. La commande est sans effet si le lien existe
déjà, elle peut donc rester à chaque déploiement.

**`config:cache`** — sûr ici, et ça ne va pas de soi : la mise en cache fige la
configuration et fait renvoyer `null` à tout appel `env()` situé hors d'un
fichier `config/`. Le projet n'en contient aucun, précisément parce que cette
panne a déjà eu lieu (`TRUSTED_PROXIES` lu dans `bootstrap/app.php`, où le
`.env` n'est pas encore chargé — voir `TrustedProxiesTest`).

**`queue:restart`** — un worker garde en mémoire le code chargé à son
démarrage. Sans ce signal, il continue d'exécuter l'ancienne version après le
déploiement.

`clip:preflight` en dernier rend un code de sortie non nul s'il reste un point
bloquant : le déploiement s'arrête dessus et le signale plutôt que de laisser
passer un `.env` de développement.

## La file d'attente

Onglet **Queue** de Ploi, un worker :

| | |
|---|---|
| Connexion | `database` |
| File | `default` |
| Processus | 1 |
| Tentatives | 3 |
| Timeout | 60 |

Les notifications aux clippeurs sont mises en file pour ne jamais retarder — ni
faire échouer — une décision de modération ou un versement. Sans worker, elles
ne partent jamais.

Le planificateur contient déjà une ligne `queue:work --stop-when-empty` chaque
minute, écrite pour les hébergements où aucun processus permanent n'est
possible. Elle devient redondante ici, mais reste sans danger :
`withoutOverlapping` empêche les deux de se marcher dessus. On peut la laisser.

## Le planificateur

Onglet **Cron**, ou le bouton *Laravel Scheduler* de Ploi, qui ajoute :

```
* * * * * php /home/ploi/VOTRE-DOMAINE/artisan schedule:run >> /dev/null 2>&1
```

**C'est la pièce vitale du système.** Elle déclenche :

| Commande | Cadence | Ce qui casse sans elle |
|---|---|---|
| `clips:sync` | horaire | les vues cessent de monter, **plus personne n'est payé** |
| `social:refresh-tokens` | 03h15 | les jetons OAuth meurent sans prévenir |
| `payouts:sync` | horaire | les webhooks PayPal perdus ne sont pas rattrapés |
| `budget:audit` | 06h00 | une divergence comptable passe inaperçue |

## Uploads : 512 Mo

Le brief accepte des rushes vidéo (`config/livewire.php`). Trois plafonds à
relever, sinon l'upload échoue sans message clair côté clippeur.

Dans **Tools → PHP → php.ini** de Ploi :

```ini
upload_max_filesize = 512M
post_max_size = 512M
max_execution_time = 300
memory_limit = 512M
```

Dans la configuration nginx du site :

```nginx
client_max_body_size 512M;
```

## Le `.env` de production

À partir de `.env.example`. Ce qui change par rapport au développement :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://VOTRE-DOMAINE

# Les cinq passages obligés, tous actifs.
REQUIRE_EMAIL_VERIFICATION=true
REQUIRE_COMPLETE_PROFILE=true
REQUIRE_ADMIN_2FA=true
REQUIRE_CREATOR_VALIDATION=true
REQUIRE_FUNDED_CAMPAIGNS=true

# Jamais de fournisseur simulé en ligne : des vues inventées
# crédiseraient de l'argent réel.
SHOW_SIMULATED_PLATFORMS=false

# nginx termine TLS en amont de PHP.
TRUSTED_PROXIES=*

MAIL_MAILER=resend
RESEND_API_KEY=...
```

Les adresses de retour OAuth n'ont **pas** à être renseignées : elles dérivent
d'`APP_URL` (`config/services.php`). Laisser `TIKTOK_REDIRECT_URI`,
`GOOGLE_REDIRECT_URI`, `YOUTUBE_REDIRECT_URI` et `INSTAGRAM_REDIRECT_URI` vides.

`APP_KEY` : la générer sur le serveur avec `php artisan key:generate`, ne jamais
reprendre celle de développement. Elle chiffre les sessions et les jetons OAuth
stockés — la partager revient à les publier.

## Une fois en ligne

1. Enregistrer `https://VOTRE-DOMAINE/oauth/tiktok/callback` dans la console
   TikTok, et `https://VOTRE-DOMAINE/auth/google/callback` chez Google.
2. Faire vérifier le domaine par TikTok (fichier dans `public/` ou balise meta
   via `TIKTOK_SITE_VERIFICATION`) — c'est ce qui débloque les URL de conditions
   d'utilisation et de confidentialité.
3. **Régénérer toutes les clés d'API.** Celles utilisées en développement ont
   circulé hors du `.env`.
4. Supprimer les comptes `@clip.test`. `clip:preflight` les signale.

## Ce que Ploi ne fait pas

- **Les sauvegardes de base.** À activer chez l'hébergeur, ou via l'onglet
  *Backups* de Ploi vers un stockage objet. Le projet manipule de l'argent
  réel : la restauration ponctuelle n'est pas une option de confort.
- **Le stockage des pièces jointes.** `FILESYSTEM_DISK=local` place les rushes
  sur le disque de la machine. Tant qu'il n'y a qu'un serveur, cela tient ; le
  jour où l'on en ajoute un, ou qu'on reconstruit celui-ci, les fichiers
  disparaissent. Un stockage objet S3-compatible est la suite logique.
