#!/usr/bin/env bash
#
# Efface les fichiers de travail de FetchClip plus vieux que sept jours.
#
# Chaque clippeur télécharge des sources et produit des montages dans son
# espace, et rien ne les efface jamais. Une cinquantaine de clippeurs à une
# dizaine de clips par semaine, une vingtaine de mégaoctets chacun : plusieurs
# gigaoctets par semaine, sans fin. Le disque est partagé avec la base et les
# journaux — le remplir n'arrête pas l'outil, il arrête le site.
#
# Sept jours laissent le temps de monter et de publier ; au-delà, un clip a
# été posté ou ne le sera pas.

set -euo pipefail

RACINE=/var/www/fetchclip/espaces
JOURS=7

[ -d "$RACINE" ] || exit 0

avant="$(du -sm "$RACINE" | cut -f1)"

# Seulement les vidéos et les fichiers temporaires : l'historique et les
# réglages de chaque clippeur restent.
find "$RACINE" -type f \( -name '*.mp4' -o -name '*.part' -o -name '.tmp-*' -o -name '*.lock' \) \
    -mtime +"$JOURS" -delete

# Les temporaires abandonnés depuis plus d'un jour : un téléchargement
# interrompu laisse un fichier partiel que rien d'autre ne nettoie.
find "$RACINE" -type f \( -name '.tmp-*' -o -name '*.part' -o -name '*.lock' \) \
    -mtime +1 -delete

apres="$(du -sm "$RACINE" | cut -f1)"

echo "$(date '+%F %T')  purge FetchClip : ${avant} Mo -> ${apres} Mo"
