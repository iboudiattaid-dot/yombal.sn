#!/usr/bin/env bash
# db-pull-prod.sh
# Exporte la BDD de production et l'importe dans MySQL local (Docker)
# Remplace les URLs production par localhost:8080

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

if [ -f "$ROOT_DIR/.env" ]; then
  export $(grep -v '^#' "$ROOT_DIR/.env" | grep -v '^$' | xargs)
fi

DUMP_FILE="$ROOT_DIR/scripts/db-init/yombal-prod.sql"
WP_PORT="${WP_PORT:-8080}"

echo "⬇️  Dump BDD production..."
mysqldump \
  -h "${PROD_DB_HOST}" \
  -P "${PROD_DB_PORT:-3306}" \
  -u "${PROD_DB_USER}" \
  -p"${PROD_DB_PASS}" \
  "${PROD_DB_NAME}" \
  > "$DUMP_FILE"

echo "🔄  Remplacement des URLs (prod → local)..."
sed -i "s|https://yombal.sn|http://localhost:${WP_PORT}|g" "$DUMP_FILE"
sed -i "s|http://yombal.sn|http://localhost:${WP_PORT}|g"  "$DUMP_FILE"

echo "📥  Import dans MySQL local..."
docker compose -f "$ROOT_DIR/docker-compose.yml" exec -T db \
  mysql -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" < "$DUMP_FILE"

# Mettre à jour siteurl et home en base
docker compose -f "$ROOT_DIR/docker-compose.yml" exec -T db \
  mysql -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" \
  -e "UPDATE wor2386_options SET option_value='http://localhost:${WP_PORT}' WHERE option_name IN ('siteurl','home');"

echo "✅ BDD production importée dans l'environnement local"
echo "   → Site accessible sur http://localhost:${WP_PORT}"
