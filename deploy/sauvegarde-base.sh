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

# Conservation sans limite de durée. Une sauvegarde compressée pèse une
# centaine de kilooctets aujourd'hui, quelques mégaoctets plus tard : des
# années de copies tiennent dans moins d'un gigaoctet. Ce qui protège
# vraiment le disque, c'est le plancher d'espace libre ci-dessous, pas une
# date d'expiration — et une date effaçait justement l'historique qu'on
# veut pouvoir retrouver en cas de litige sur un versement ancien.
ESPACE_MINIMUM_MO=5000

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

# Rien n'est supprimé, sauf si le disque descend sous le plancher : on
# retire alors les plus anciennes, une par une, en gardant toujours au moins
# les soixante dernières. Ça n'arrivera qu'après des années, ou si le disque
# se remplit pour une autre raison — et dans ce cas, mieux vaut perdre une
# copie de l'an dernier que bloquer la base d'aujourd'hui.
libre_mo() { df -Pm "$DOSSIER" | awk 'NR==2 {print $4}'; }

while [ "$(libre_mo)" -lt "$ESPACE_MINIMUM_MO" ]     && [ "$(ls -1 "$DOSSIER"/clipadem-*.sql.gz 2>/dev/null | wc -l)" -gt 60 ]; do
    plus_ancienne="$(ls -1tr "$DOSSIER"/clipadem-*.sql.gz | head -1)"
    rm -f "$plus_ancienne"
    echo "$(date '+%F %T')  disque bas, retiree : $(basename "$plus_ancienne")"
done

echo "$(date '+%F %T')  sauvegarde : $(basename "$FICHIER") ($(du -h "$FICHIER" | cut -f1))"
