# Déploiement sur un VPS OVH (Ubuntu)

Serveur : `vps-30a0b74d.vps.ovh.ca` — `148.113.184.204` — utilisateur `ubuntu`.

Ce document remplace `deploiement-ploi.md` : même application, hébergement
différent. Ce qu'il faut vérifier **avant d'ouvrir au public** reste décrit dans
le README, section « Avant d'ouvrir au public », et contrôlé par
`php artisan clip:preflight`.

## 0. Un avertissement sur la méthode

VS Code Remote-SSH permet d'éditer les fichiers du serveur comme s'ils étaient
locaux. C'est très pratique pour l'administration — lire un log, corriger une
ligne de nginx — et **c'est un mauvais endroit pour développer**.

Trois raisons, propres à ce projet :

- Le serveur exécute des paiements réels. Une modification enregistrée par
  réflexe s'applique immédiatement, sans test ni relecture.
- Anas travaille sur le même dépôt. Du code qui n'existe que sur le serveur
  n'est pas dans git : il sera écrasé au prochain déploiement, ou il écrasera
  le sien.
- La suite compte 397 tests. Ils ne tournent pas quand on édite en production.

**La bonne boucle :** développer en local, pousser sur GitHub, puis `git pull`
sur le serveur. Remote-SSH sert à administrer et à déclencher le déploiement,
pas à écrire le code.

## 1. Prérequis : un nom de domaine

À faire **avant** la suite. TikTok et Google refusent une adresse IP comme
adresse de retour OAuth, et TikTok exige en plus de vérifier la propriété d'un
domaine.

Achète un domaine, puis crée un enregistrement `A` pointant vers
`148.113.184.204`. Sans lui, tout le reste fonctionne sauf la connexion des
comptes TikTok — c'est-à-dire la fonction centrale du site.

## 2. Connexion SSH par clé, depuis Windows

### Générer la clé (PowerShell, sur ton PC)

```powershell
ssh-keygen -t ed25519 -C "ilias-clipadem"
```

Accepte le chemin proposé (`C:\Users\bouna\.ssh\id_ed25519`). Une phrase de
passe est recommandée.

### Déposer la clé publique sur le serveur

`ssh-copy-id` n'existe pas sous Windows. Cette commande fait la même chose —
elle demandera le mot de passe OVH, une dernière fois :

```powershell
type $env:USERPROFILE\.ssh\id_ed25519.pub | ssh ubuntu@148.113.184.204 "mkdir -p ~/.ssh && chmod 700 ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
```

### Nommer le serveur

Dans `C:\Users\bouna\.ssh\config` :

```sshconfig
Host clipadem
    HostName 148.113.184.204
    User ubuntu
    IdentityFile ~/.ssh/id_ed25519
    ServerAliveInterval 60
```

Vérifie que `ssh clipadem` ouvre une session **sans mot de passe** avant de
continuer.

### VS Code

1. Extension **Remote - SSH** (`ms-vscode-remote.remote-ssh`).
2. `F1` → *Remote-SSH: Connect to Host* → `clipadem`.
3. Une fois connecté, *File → Open Folder* → `/var/www/clipadem`.

## 3. Sécuriser le serveur

```bash
sudo apt update && sudo apt full-upgrade -y
sudo timedatectl set-timezone Europe/Paris
```

### Fermer l'authentification par mot de passe

```bash
sudo tee /etc/ssh/sshd_config.d/99-clipadem.conf >/dev/null <<'EOF'
PasswordAuthentication no
PermitRootLogin no
EOF

sudo systemctl restart ssh
```

> **Garde la session actuelle ouverte** et vérifie la connexion par clé depuis
> une **seconde** fenêtre. Si la clé ne fonctionne pas et que tu as fermé la
> première, tu es enfermé dehors : il faut alors passer par la console KVM
> d'OVH pour rentrer.

### Pare-feu

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### Bannissement automatique et mises à jour de sécurité

```bash
sudo apt install -y fail2ban unattended-upgrades
sudo systemctl enable --now fail2ban
sudo dpkg-reconfigure -plow unattended-upgrades
```

## 4. Installer la pile

```bash
sudo apt install -y nginx mysql-server git unzip curl
```

### PHP 8.4

Vérifie d'abord ce que propose ta version d'Ubuntu :

```bash
apt-cache policy php8.4-fpm
```

Si le paquet est introuvable, ajoute le dépôt d'Ondřej Surý :

```bash
sudo add-apt-repository ppa:ondrej/php -y && sudo apt update
```

Puis, dans tous les cas :

```bash
sudo apt install -y php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml \
                    php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl
```

`php8.4-gd` n'est pas facultatif : c'est lui qui recadre les avatars
(`intervention/image`).

### Composer et Node

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

Node ne sert qu'à compiler les assets au déploiement, jamais à l'exécution.

### Base de données

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE clip CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'clip'@'localhost' IDENTIFIED BY 'UN_MOT_DE_PASSE_LONG_ET_ALEATOIRE';
GRANT ALL PRIVILEGES ON clip.* TO 'clip'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 5. Installer l'application

```bash
sudo mkdir -p /var/www/clipadem
sudo chown ubuntu:ubuntu /var/www/clipadem

git clone https://github.com/ILIAS-ma/Clip_adem.git /var/www/clipadem
cd /var/www/clipadem

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
```

Renseigne ensuite le `.env` (section 8), puis :

```bash
php artisan migrate --force
php artisan storage:link

sudo chown -R www-data:www-data storage bootstrap/cache
```

`storage:link` n'est pas optionnel : les pièces jointes des briefs passent par
le disque `public`. Sans ce lien, les fichiers sont bien enregistrés mais
**aucun n'est téléchargeable** — le clippeur reçoit une 404.

## 6. nginx, PHP et HTTPS

### Le site

`/etc/nginx/sites-available/clipadem` :

```nginx
server {
    listen 80;
    server_name VOTRE-DOMAINE;
    root /var/www/clipadem/public;

    index index.php;
    charset utf-8;

    # Rushes vidéo joints aux briefs.
    client_max_body_size 512M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/clipadem /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

### Les plafonds PHP

Dans `/etc/php/8.4/fpm/php.ini` :

```ini
upload_max_filesize = 512M
post_max_size = 512M
max_execution_time = 300
memory_limit = 512M
```

```bash
sudo systemctl restart php8.4-fpm
```

Les trois plafonds doivent être relevés ensemble. Il suffit d'en oublier un
pour que l'envoi échoue sans message clair côté clippeur.

### Certificat

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d VOTRE-DOMAINE
```

## 7. Le planificateur et la file d'attente

### Cron — la pièce vitale

```bash
crontab -e
```

```cron
* * * * * cd /var/www/clipadem && php artisan schedule:run >> /dev/null 2>&1
```

Il déclenche cinq traitements, dont le relevé des vues. **Sans lui, les vues
cessent de monter et plus personne n'est payé.**

### Worker de file d'attente

`/etc/systemd/system/clipadem-worker.service` :

```ini
[Unit]
Description=Clip Adem — file d'attente
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/clipadem
ExecStart=/usr/bin/php artisan queue:work --tries=3 --max-time=3600 --sleep=3

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now clipadem-worker
```

La ligne `queue:work --stop-when-empty` du planificateur devient alors
redondante, mais reste sans danger : `withoutOverlapping` empêche les deux de
se marcher dessus.

## 8. Les identifiants et variables d'environnement

**La règle :** le `.env` vit sur le serveur, uniquement. Il est déjà dans
`.gitignore` (ainsi que `.env.backup*`), et l'historique du dépôt en est
exempt — vérifié.

```bash
chmod 600 /var/www/clipadem/.env
```

Ce qui change par rapport au développement :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://VOTRE-DOMAINE

DB_DATABASE=clip
DB_USERNAME=clip
DB_PASSWORD=celui_cree_en_section_4

# Les cinq passages obligés, tous actifs.
REQUIRE_EMAIL_VERIFICATION=true
REQUIRE_COMPLETE_PROFILE=true
REQUIRE_ADMIN_2FA=true
REQUIRE_CREATOR_VALIDATION=true
REQUIRE_FUNDED_CAMPAIGNS=true

# Jamais de fournisseur simulé en ligne : des vues inventées
# crédiseraient de l'argent réel.
SHOW_SIMULATED_PLATFORMS=false

# nginx et PHP tournent sur la même machine : rien à déclarer.
TRUSTED_PROXIES=

MAIL_MAILER=resend
RESEND_API_KEY=...
```

Trois points qui comptent :

- **`APP_KEY` se génère sur le serveur.** Ne reprends jamais celle de
  développement : elle chiffre les sessions et les jetons OAuth stockés en base.
  La partager revient à les publier.
- **Les adresses de retour OAuth n'ont pas à être renseignées.** Elles dérivent
  d'`APP_URL` (`config/services.php`). Laisse `TIKTOK_REDIRECT_URI`,
  `GOOGLE_REDIRECT_URI`, `YOUTUBE_REDIRECT_URI` et `INSTAGRAM_REDIRECT_URI`
  vides.
- **Régénère les clés TikTok et Google.** Celles utilisées en développement ont
  circulé hors du `.env`.

### Ne jamais lire une variable d'environnement hors des fichiers de configuration

Dans le code, on écrit `config('services.tiktok.client_key')`, jamais
`env('TIKTOK_CLIENT_KEY')`. La mise en cache de la configuration
(`php artisan config:cache`, utilisée au déploiement) fige les valeurs et fait
renvoyer `null` à tout `env()` situé ailleurs qu'en `config/`.

Le dépôt n'en contient aucun — c'est vérifiable et c'est une panne déjà vécue,
avec `TRUSTED_PROXIES` lu dans `bootstrap/app.php`, où le `.env` n'est pas
encore chargé. `TrustedProxiesTest` existe pour qu'elle ne revienne pas.

## 9. Déployer une mise à jour

```bash
cd /var/www/clipadem

git pull origin main

composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan queue:restart
sudo systemctl reload php8.4-fpm

php artisan clip:preflight
```

`clip:preflight` rend un code de sortie non nul s'il reste un point bloquant :
le déploiement s'arrête dessus plutôt que de laisser passer un réglage de
développement.

## 10. Une fois en ligne

1. Enregistrer `https://VOTRE-DOMAINE/oauth/tiktok/callback` dans la console
   TikTok, et `https://VOTRE-DOMAINE/auth/google/callback` chez Google.
2. Faire vérifier le domaine par TikTok.
3. Supprimer les comptes `@clip.test` — `clip:preflight` les signale.
4. Mettre en place une sauvegarde de la base. Le site manipule de l'argent
   réel ; OVH propose des snapshots, et un `mysqldump` quotidien vers un
   stockage distant coûte quelques centimes.
