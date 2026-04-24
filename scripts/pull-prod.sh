#!/usr/bin/env bash
# pull-prod.sh
# Récupère les mu-plugins depuis la production vers le dépôt local

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

if [ -f "$ROOT_DIR/.env" ]; then
  export $(grep -v '^#' "$ROOT_DIR/.env" | grep -v '^$' | xargs)
fi

LOCAL_MU="$ROOT_DIR/wp-content/mu-plugins"
REMOTE_MU="${PROD_REMOTE_MU:-/home/yombalr/www/wp-content/mu-plugins/}"

if [ -z "${PROD_FTP_PASS:-}" ]; then
  echo "⚠️  PROD_FTP_PASS non défini dans .env"
  exit 1
fi

mkdir -p "$LOCAL_MU"

FTP_CMD=$(mktemp)
cat > "$FTP_CMD" << EOF
open $PROD_FTP_HOST
user $PROD_FTP_USER $PROD_FTP_PASS
cd $REMOTE_MU
lcd $LOCAL_MU
mget yombal-*.php
mget _fix-rest.php
bye
EOF

echo "⬇️  Pull mu-plugins depuis prod..."
ftp -n < "$FTP_CMD"
rm -f "$FTP_CMD"
echo "✅ Pull terminé dans $LOCAL_MU"
