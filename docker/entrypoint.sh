#!/bin/bash
set -e

echo "──────────────────────────────────────────"
echo "  DCS — Container Startup"
echo "──────────────────────────────────────────"

# ── Ensure Nginx run dir & Laravel storage directories exist with permissions ─
mkdir -p /run/nginx /var/log/nginx /var/log/supervisor
mkdir -p storage/framework/views storage/framework/sessions storage/framework/cache/data storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache

# ── Install / sync PHP dependencies ─────────────────────────────────────────
echo "[1/5] Running composer install..."
if [ ! -f "vendor/autoload.php" ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    echo "  vendor/ already exists — skipping install."
fi

# ── Generate APP_KEY if not set ──────────────────────────────────────────────
if [ -z "$APP_KEY" ]; then
    echo ""
    echo "  ⚠  APP_KEY is not set."
    echo "     After startup, run once:"
    echo "       docker compose exec app php artisan key:generate"
    echo "     Then copy the generated key into .env.docker as APP_KEY=..."
    echo ""
fi

# ── Wait for DB and run migrations ───────────────────────────────────────────
echo "[2/5] Waiting for database..."
until php artisan migrate --force 2>/dev/null; do
    echo "  DB not ready yet — retrying in 3s..."
    sleep 3
done
echo "  Migrations OK."

# ── Storage link ─────────────────────────────────────────────────────────────
echo "[3/5] Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

# ── Runtime PHP / Nginx config (bind-mount friendly) ─────────────────────────
if [ -f docker/php/conf.d/99-custom.ini ]; then
    cp docker/php/conf.d/99-custom.ini /usr/local/etc/php/conf.d/99-custom.ini
fi
if [ -f docker/nginx/default.conf ]; then
    cp docker/nginx/default.conf /etc/nginx/http.d/default.conf
fi

# ── Cache ─────────────────────────────────────────────────────────────────────
echo "[4/5] Caching config..."
php artisan config:cache

echo "[5/5] Starting server..."
echo "──────────────────────────────────────────"

exec "$@"