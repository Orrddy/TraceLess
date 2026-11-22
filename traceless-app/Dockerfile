# syntax=docker/dockerfile:1.7
#
# Production image for TraceLess.
#
# Build context is the REPOSITORY ROOT (not traceless-app), because the image
# must also contain python-services/ with its upstream scanner datasets. A CI
# step clones those datasets into python-services/upstream before building.
#
#   docker build -f traceless-app/Dockerfile -t traceless .
#
# The default command runs the full stack (web + queue + scheduler) in one
# container, which is what single-app hosts like Altare provide. Compose can
# still run web/queue/scheduler separately by overriding the command.

# ---------------------------------------------------------------------------
# 1. Front-end assets
# ---------------------------------------------------------------------------
FROM node:20-bookworm-slim AS assets

WORKDIR /app

COPY traceless-app/package.json traceless-app/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY traceless-app/vite.config.js traceless-app/postcss.config.js traceless-app/tailwind.config.js ./
COPY traceless-app/resources ./resources
COPY traceless-app/public ./public

RUN npm run build

# ---------------------------------------------------------------------------
# 2. Composer dependencies + application source
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY traceless-app/composer.json traceless-app/composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --ignore-platform-reqs

COPY traceless-app/ ./
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---------------------------------------------------------------------------
# 3. Runtime: nginx + PHP-FPM + queue + scheduler under supervisor
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-bookworm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        ca-certificates \
        git \
        unzip \
        python3 \
        python3-venv \
        python3-pip \
        libpq-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql opcache pcntl zip \
    && rm -f /etc/nginx/sites-enabled/default /etc/nginx/sites-available/default \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Deep scans need the vendored engines, which need these packages.
COPY traceless-app/docker/scanner-requirements.txt /tmp/scanner-requirements.txt
RUN python3 -m venv /opt/traceless/venv \
    && /opt/traceless/venv/bin/pip install --no-cache-dir --upgrade pip \
    && /opt/traceless/venv/bin/pip install --no-cache-dir -r /tmp/scanner-requirements.txt \
    && rm -f /tmp/scanner-requirements.txt

# Laravel reads runtime environment variables inside FPM workers.
COPY traceless-app/docker/php/zz-env.conf /usr/local/etc/php-fpm.d/zz-env.conf
COPY traceless-app/docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY traceless-app/docker/nginx/default.conf /etc/nginx/sites-enabled/default
COPY traceless-app/docker/supervisord.conf /etc/supervisord-role.conf
COPY traceless-app/docker/supervisord-all.conf /etc/supervisord-all.conf
COPY traceless-app/docker/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# Scanner worker and its datasets. worker.py resolves its dataset relative to
# this directory, so the layout must stay intact.
COPY python-services /var/www/html/python-services

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/app/public \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_ROLE=web \
    LOG_CHANNEL=stderr \
    RUN_MIGRATIONS=true \
    TRACELESS_PYTHON_BINARY=/opt/traceless/venv/bin/python \
    TRACELESS_SCANNER_SCRIPT=/var/www/html/python-services/scanner/worker.py

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=25s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["docker-entrypoint"]

# Full stack in one container (web + queue + scheduler), for hosts that run a
# single app process. Compose overrides this with /etc/supervisord-role.conf.
CMD ["supervisord", "-c", "/etc/supervisord-all.conf"]
