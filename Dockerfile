FROM php:8.4-fpm-alpine

# ── Copy Node.js from the node image ─────────────────────────────────────────
COPY --from=node:20-alpine /usr/local/bin/node /usr/local/bin/node
COPY --from=node:20-alpine /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# ── System dependencies & Nginx & Supervisor ─────────────────────────────────
RUN apk add --no-cache \
        bash \
        curl \
        git \
        nginx \
        supervisor \
        libpng-dev \
        libxml2-dev \
        oniguruma-dev \
        libzip-dev \
        icu-dev \
        zip \
        unzip \
        postgresql-client \
        postgresql-dev \
        tesseract-ocr \
        tesseract-ocr-data-eng \
        ghostscript \
        imagemagick \
        imagemagick-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
    # Install build deps temporarily for pecl
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apk del .build-deps \
    && mkdir -p /run/nginx /var/log/supervisor /var/log/nginx

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ── Nginx & Supervisor Configs ────────────────────────────────────────────────
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/conf.d/99-custom.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/supervisord.conf /etc/supervisord.conf

# ── Startup script ────────────────────────────────────────────────────────────
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]