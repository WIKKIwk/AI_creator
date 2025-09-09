#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DC="bash ./bin/dc -f docker-compose-prod.yml"

echo "[fix-admin] Rebuilding and starting php+nginx..."
$DC up -d --build php nginx

echo "[fix-admin] Waiting for PHP container to be ready..."
ATTEMPTS=0
until $DC exec -T php php -v >/dev/null 2>&1; do
  ATTEMPTS=$((ATTEMPTS+1))
  if [ $ATTEMPTS -ge 60 ]; then
    echo "PHP not ready after 60s" >&2
    exit 1
  fi
  sleep 1
done

echo "[fix-admin] Installing composer deps (inside php)..."
$DC exec -T php composer install --no-interaction --prefer-dist --no-ansi --no-progress || true

echo "[fix-admin] Optimize cache clear..."
$DC exec -T php php artisan optimize:clear || true

echo "[fix-admin] Running migrations..."
$DC exec -T php php artisan migrate --force

echo "[fix-admin] Seeding database (ensures admin exists via .env ADMIN_EMAIL/ADMIN_PASSWORD)..."
$DC exec -T php php artisan db:seed --force || true

echo "[fix-admin] Regenerating auth codes for all users..."
$DC exec -T php php artisan app:regenerate-auth-codes || true

echo "[fix-admin] Showing admin auth code..."
ADMIN_EMAIL=$(grep -E '^ADMIN_EMAIL=' .env | sed -E 's/^ADMIN_EMAIL=//')
if [ -n "$ADMIN_EMAIL" ]; then
  $DC exec -T php php artisan app:show-auth-codes --email="$ADMIN_EMAIL" || true
else
  echo "ADMIN_EMAIL not set in .env; showing all codes"
  $DC exec -T php php artisan app:show-auth-codes || true
fi

echo "[fix-admin] Done. Open http://localhost:${APP_PORT:-8080}/admin/login"

