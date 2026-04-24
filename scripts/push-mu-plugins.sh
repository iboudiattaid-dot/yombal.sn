#!/usr/bin/env bash
# push-mu-plugins.sh
# Envoie les mu-plugins locaux vers la production via FTP
# Usage: ./scripts/push-mu-plugins.sh [fichier.php]  (sans arg = tous les fichiers)

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

# Charger les variables d'environnement
if [ -f "$ROOT_DIR/.env" ]; then
  export $(grep -v '^#' "$ROOT_DIR/.env" | grep -v '^$' | xargs)
fi

LOCAL_MU="$ROOT_DIR/wp-content/mu-plugins"
REMOTE_MU="${PROD_REMOTE_MU:-/home/yombalr/www/wp-content/mu-plugins/}"

if [ -z "${PROD_FTP_PASS:-}" ]; then
  echo "⚠️  PROD_FTP_PASS non défini dans .env"
  exit 1
fi

# Construire le script FTP
FTP_CMD=$(mktemp)

cat > "$FTP_CMD" << EOF
open $PROD_FTP_HOST
user $PROD_FTP_USER $PROD_FTP_PASS
cd $REMOTE_MU
EOF

if [ -n "${1:-}" ]; then
  # Envoyer un seul fichier
  echo "put $LOCAL_MU/$1" >> "$FTP_CMD"
  echo "Envoi de $1..."
else
  # Envoyer tous les mu-plugins yombal-*.php et _fix-rest.php
  for f in "$LOCAL_MU"/yombal-*.php "$LOCAL_MU"/_fix-rest.php; do
    [ -f "$f" ] && echo "put $f $(basename $f)" >> "$FTP_CMD"
  done
  echo "Envoi de tous les mu-plugins..."
fi

echo "bye" >> "$FTP_CMD"

ftp -n < "$FTP_CMD"
rm -f "$FTP_CMD"

echo "✅ Push terminé vers $PROD_FTP_HOST$REMOTE_MU"
