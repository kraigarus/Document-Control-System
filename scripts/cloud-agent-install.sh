#!/usr/bin/env bash
#
# Cloud Agent install phase for the Document Control System (Laravel 13 + Vite).
#
# Idempotent: safe to run repeatedly and against a cached/partially-prepared VM
# (for example when it bakes an environment-build snapshot). It installs system
# toolchains, PHP dependencies, front-end assets, and prepares the MySQL
# database. No long-running process is started here (see cloud-agent-start.sh).
set -euo pipefail

cd "$(dirname "$0")/.."

export DEBIAN_FRONTEND=noninteractive

echo "==> Ensuring system packages (PHP 8.4, MySQL, OCR/PDF toolchain)"
# The locked dependencies (symfony/* v8.x) require PHP >= 8.4.1, matching the
# repository Dockerfile (php:8.4-fpm). Ubuntu 24.04 ships PHP 8.3, so pull 8.4
# from the well-known ondrej/sury PPA when it is not already available.
if ! php -v 2>/dev/null | grep -q 'PHP 8.4'; then
    sudo apt-get update -qq
    sudo apt-get install -y -qq software-properties-common
    sudo add-apt-repository -y ppa:ondrej/php
    sudo apt-get update -qq
fi

sudo apt-get install -y -qq \
    php8.4-cli php8.4-common php8.4-bcmath php8.4-curl php8.4-gd php8.4-imagick \
    php8.4-intl php8.4-mbstring php8.4-mysql php8.4-sqlite3 php8.4-xml php8.4-zip \
    tesseract-ocr ghostscript imagemagick mysql-server unzip zip
sudo update-alternatives --set php /usr/bin/php8.4 >/dev/null 2>&1 || true

echo "==> Ensuring Composer"
if ! command -v composer >/dev/null 2>&1; then
    php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
    sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f /tmp/composer-setup.php
fi

echo "==> Configuring MySQL for container/overlay filesystems"
# Pods that boot from an environment-build snapshot run on overlayfs, which does
# not support O_DIRECT or Linux native AIO. Without these overrides InnoDB
# aborts during file I/O / redo-log recovery with "OS error 22 (Invalid
# argument)". Writing the drop-in here bakes it into the build snapshot.
sudo tee /etc/mysql/mysql.conf.d/zz-cloud-agent.cnf >/dev/null <<'CNF'
[mysqld]
innodb_flush_method = fsync
innodb_use_native_aio = 0
CNF

echo "==> Starting MySQL and provisioning databases/user"
bash "$(dirname "$0")/cloud-agent-start.sh"
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS document_control_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'dcs_user'@'localhost' IDENTIFIED BY 'dcs_password';
CREATE USER IF NOT EXISTS 'dcs_user'@'127.0.0.1' IDENTIFIED BY 'dcs_password';
GRANT ALL PRIVILEGES ON document_control_system.* TO 'dcs_user'@'localhost';
GRANT ALL PRIVILEGES ON document_control_system.* TO 'dcs_user'@'127.0.0.1';
GRANT ALL PRIVILEGES ON testing.* TO 'dcs_user'@'localhost';
GRANT ALL PRIVILEGES ON testing.* TO 'dcs_user'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist

echo "==> Preparing .env"
if [ ! -f .env ]; then
    cp .env.example .env
    sed -i 's/^DB_CONNECTION=sqlite/DB_CONNECTION=mysql/' .env
    sed -i 's/^# DB_HOST=127.0.0.1/DB_HOST=127.0.0.1/' .env
    sed -i 's/^# DB_PORT=3306/DB_PORT=3306/' .env
    sed -i 's/^# DB_DATABASE=laravel/DB_DATABASE=document_control_system/' .env
    sed -i 's/^# DB_USERNAME=root/DB_USERNAME=dcs_user/' .env
    sed -i 's/^# DB_PASSWORD=/DB_PASSWORD=dcs_password/' .env
fi
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

echo "==> Running migrations and seeding the admin account"
php artisan migrate --force
php artisan db:seed --class=AdminAccountSeeder --force

echo "==> Building front-end assets"
npm ci
npm run build

echo "==> Clearing cached configuration"
php artisan config:clear

# Shut MySQL down cleanly so that a snapshot taken right after install (for an
# environment build) captures a consistent InnoDB data directory. The start
# phase brings MySQL back up on the next boot.
echo "==> Stopping MySQL for a consistent data directory"
sudo mysqladmin shutdown 2>/dev/null || true

echo "==> Install phase complete"
