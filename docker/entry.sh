#!/bin/bash
set -e

# ── Fix permissions ──
# PHP-FPM runs as www-data, so storage/cache must be writable
if [ -d /var/www/html/storage ]; then
    chown -R www-data:www-data /var/www/html/storage
    chown -R www-data:www-data /var/www/html/bootstrap/cache
fi

# ── Install Composer dependencies ──
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo ">> Installing composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

# ── Generate app key if missing ──
if ! grep -q "APP_KEY=base64" /var/www/html/.env 2>/dev/null; then
    echo ">> Generating app key..."
    php artisan key:generate --no-interaction
fi

if [ -d /var/www/html/storage ]; then
    chown -R www-data:www-data /var/www/html/storage
    chown -R www-data:www-data /var/www/html/bootstrap/cache
    find /var/www/html/storage/app/public -type d -exec chmod 755 {} \;
    find /var/www/html/storage/app/public -type f -exec chmod 644 {} \;
fi

if [ -L /var/www/html/public/storage ]; then
    target=$(readlink /var/www/html/public/storage)
    if [[ "$target" != /var/www/html/* ]]; then
        echo ">> Fixing broken storage symlink (was pointing to host path)..."
        rm /var/www/html/public/storage
        php artisan storage:link
    fi
elif [ -e /var/www/html/public/storage ]; then
    echo ">> public/storage exists but isn't a symlink — leaving as-is, check manually."
else
    php artisan storage:link
fi

# ── Wait for MySQL to be ready ──
echo ">> Waiting for MySQL..."
until php artisan db:monitor > /dev/null 2>&1; do
    sleep 2
done
echo ">> MySQL is ready."

# ── Run migrations ──
echo ">> Running migrations..."
php artisan migrate --force

# ── Cache config for performance (optional) ──
# php artisan config:cache
# php artisan route:cache
# php artisan view:cache

echo ">> PHP-FPM is starting..."

# Hand off to the default CMD (php-fpm)
exec docker-php-entrypoint "$@"
