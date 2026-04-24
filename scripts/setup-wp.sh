#!/usr/bin/env bash
# setup-wp.sh
# Configure WordPress local via WP-CLI après le premier démarrage Docker
# Usage: ./scripts/setup-wp.sh

set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

if [ -f "$ROOT_DIR/.env" ]; then
  export $(grep -v '^#' "$ROOT_DIR/.env" | grep -v '^$' | xargs)
fi

WP_PORT="${WP_PORT:-8080}"
WP_URL="http://localhost:${WP_PORT}"

WPCLI="docker compose -f $ROOT_DIR/docker-compose.yml run --rm wpcli wp --allow-root"

echo "⏳ Attente démarrage WordPress..."
sleep 10

echo "🔧 Installation WordPress..."
$WPCLI core install \
  --url="$WP_URL" \
  --title="Yombal Dev" \
  --admin_user="${WP_ADMIN_USER:-admin_yombal2025}" \
  --admin_password="${WP_ADMIN_PASSWORD:?Définir WP_ADMIN_PASSWORD dans .env}" \
  --admin_email="${WP_ADMIN_EMAIL:-dev@yombal.sn}" \
  --skip-email || echo "(déjà installé)"

echo "⚙️  Configuration..."
$WPCLI option update siteurl "$WP_URL"
$WPCLI option update home    "$WP_URL"
$WPCLI option update blogname "Yombal Dev"
$WPCLI option update woocommerce_store_address "Dakar, Sénégal"
$WPCLI option update woocommerce_default_country "SN"
$WPCLI option update woocommerce_currency "XOF"
$WPCLI option update woocommerce_currency_pos "right_space"

echo "🔌 Activation plugins essentiels..."
$WPCLI plugin activate woocommerce || true
$WPCLI plugin activate elementor || true
$WPCLI plugin activate wc-frontend-manager || true
$WPCLI plugin activate wc-frontend-manager-ultimate || true
$WPCLI plugin activate wc-multivendor-marketplace || true
$WPCLI plugin activate advanced-custom-fields || true

echo "🎨 Activation du thème..."
$WPCLI theme activate royal-elementor-addons 2>/dev/null || \
$WPCLI theme activate twentytwentyfour || true

echo "📬 Config email SMTP (MailHog)..."
$WPCLI option update admin_email "dev@yombal.sn"

echo "✅ WordPress local configuré → $WP_URL"
echo "   Admin : $WP_URL/wp-admin/"
echo "   Login : admin_yombal2025 / YombalAdmin2025"
