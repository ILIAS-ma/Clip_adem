#!/usr/bin/env bash
#
# Sauvegarde quotidienne de la base.
#
# La base porte le grand livre : chaque centime crédité à un clippeur, chaque
# versement, chaque campagne financée. Sans copie, une seule fausse manœuvre
# — une migration ratée, un disque qui lâche — efface ce qu'on doit à des
# gens, sans moyen de le reconstituer.
#
# --single-transaction : la copie est cohérente sans bloquer le site. Une
# sauvegarde qui verrouille les tables arrêterait les crédits pendant qu'elle
# tourne.
#
# Les identifiants ne passent jamais en argument : ils seraient visibles de
# tout utilisateur de la machine dans la liste des processus. Ils sont lus du
# .env et écrits dans un fichier d'options temporaire, lisible par le seul
# propriétaire.

set -euo pipefail

ENV=/var/www/clipadem/.env
DOSSIER=/home/ubuntu/sauvegardes
GARDER_JOURS=14

lire() { grep -E "^$1=" "$ENV" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

HOTE="$(lire DB_HOST)"
PORT="$(lire DB_PORT)"
BASE="$(lire DB_DATABASE)"
UTIL="$(lire DB_USERNAME)"
MDP="$(lire DB_PASSWORD)"

mkdir -p "$DOSSIER"
chmod 700 "$DOSSIER"

OPTIONS="$(mktemp)"
chmod 600 "$OPTIONS"
trap 'rm -f "$OPTIONS"' EXIT

printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' \
    "${HOTE:-127.0.0.1}" "${PORT:-3306}" "$UTIL" "$MDP" > "$OPTIONS"

FICHIER="$DOSSIER/clipadem-$(date +%Y-%m-%d_%H%M).sql.gz"

# Écrit sous un nom provisoire puis renommé : une sauvegarde interrompue ne
# doit jamais pouvoir passer pour une sauvegarde complète.
mysqldump --defaults-extra-file="$OPTIONS" \
    --single-transaction --quick --routines --triggers --no-tablespaces \
    "$BASE" | gzip -6 > "$FICHIER.partiel"

mv "$FICHIER.partiel" "$FICHIER"
chmod 600 "$FICHIER"

# On ne supprime les anciennes qu'après avoir réussi la nouvelle : dans
# l'autre ordre, une série d'échecs viderait le dossier.
find "$DOSSIER" -name 'clipadem-*.sql.gz' -mtime +"$GARDER_JOURS" -delete

echo "$(date '+%F %T')  sauvegarde : $(basename "$FICHIER") ($(du -h "$FICHIER" | cut -f1))"
