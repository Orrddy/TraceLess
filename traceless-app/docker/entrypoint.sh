#!/bin/sh
set -e

APP_ROLE="${APP_ROLE:-web}"
APP_ENV="${APP_ENV:-production}"

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is not set. Run 'php artisan key:generate --show' and provide the value." >&2
    exit 1
fi

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Cache configuration/routes/views once for the web container. Queue and
# scheduler containers share the image but are started with APP_ROLE set.
if [ "$APP_ROLE" = "web" ] && [ "$APP_ENV" = "production" ]; then
    php artisan optimize --no-interaction
fi

if [ ! -L public/storage ] && [ -d storage/app/public ]; then
    php artisan storage:link --no-interaction || true
fi

exec "$@"
