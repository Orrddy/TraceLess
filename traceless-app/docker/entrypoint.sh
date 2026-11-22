#!/bin/sh
set -e

APP_ROLE="${APP_ROLE:-web}"
APP_ENV="${APP_ENV:-production}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is not set. Run 'php artisan key:generate --show' and provide the value." >&2
    exit 1
fi

cd /var/www/html

# ---------------------------------------------------------------------------
# Persistent storage
# Every host that offers a persistent volume mounts it at /data. Reports,
# uploads and logs must survive redeploys, so the two writable storage folders
# are symlinked there. Sessions and compiled views stay ephemeral.
# ---------------------------------------------------------------------------
if [ -d /data ]; then
    mkdir -p /data/storage/app/public /data/storage/logs
    if [ ! -L storage/app ]; then
        rm -rf storage/app
        ln -s /data/storage/app storage/app
    fi
    if [ ! -L storage/logs ]; then
        rm -rf storage/logs
        ln -s /data/storage/logs storage/logs
    fi
    chmod -R ug+rwX /data/storage 2>/dev/null || true
    echo "[entrypoint] storage persisted on /data"
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    storage/logs \
    bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache
fi

# ---------------------------------------------------------------------------
# Database migrations
# Retries because a managed database can be slow to accept the first connect.
# A failed migration is fatal: serving a half-migrated app is worse than not
# starting.
# ---------------------------------------------------------------------------
if [ "$RUN_MIGRATIONS" = "true" ] && [ "$APP_ROLE" = "web" ] && [ "$APP_ENV" = "production" ]; then
    attempt=1
    until php artisan migrate --force --no-interaction; do
        if [ "$attempt" -ge 5 ]; then
            echo "FATAL: migrations failed after ${attempt} attempts." >&2
            exit 1
        fi
        echo "[entrypoint] migration attempt ${attempt} failed; retrying in 5s" >&2
        attempt=$((attempt + 1))
        sleep 5
    done
fi

# ---------------------------------------------------------------------------
# Caches
# Config only: route caching is not possible while routes use closures.
# ---------------------------------------------------------------------------
if [ "$APP_ROLE" = "web" ] && [ "$APP_ENV" = "production" ]; then
    php artisan config:cache || true
    php artisan view:cache || true
fi

if [ ! -L public/storage ] && [ -d storage/app/public ]; then
    php artisan storage:link --no-interaction || true
fi

exec "$@"
