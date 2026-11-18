#!/usr/bin/env bash
# Foreground supervisor for one weeny.cloud release.
#
# weeny requires the start command to stay in the foreground, so Caddy runs as
# the main process and everything else (queue worker, scheduler) is a supervised
# background loop that restarts on exit.
#
# Everything is derived from this file's location, so no install-time paths are
# baked in and a re-push to a new release directory keeps working.

set -u

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export APP_ROOT
export PATH="/usr/local/bin:/usr/bin:/bin:$PATH"

LOG_DIR="$APP_ROOT/storage/logs"
mkdir -p "$LOG_DIR"

log() { printf '[run] %s\n' "$*" >&2; }

# --- PHP-FPM --------------------------------------------------------------
if ! ls /run/php/*.sock >/dev/null 2>&1; then
	log "php-fpm socket missing, trying to start the service"
	(service php-fpm start || service "php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm" start || true) >/dev/null 2>&1
fi

PHP_SOCKET="$(ls /run/php/*.sock 2>/dev/null | head -n1 || true)"
PHP_SOCKET="${PHP_SOCKET#unix:}"

if [ -z "$PHP_SOCKET" ]; then
	log "no php-fpm socket found; refusing to start with a broken FastCGI upstream"
	exit 1
fi

export PHP_SOCKET
log "app=$APP_ROOT fpm=$PHP_SOCKET"

# --- Python scanner -------------------------------------------------------
# Deep scans need the extras installed by install.sh. The curated username scan
# only needs the standard library, so a missing venv degrades gracefully.
if [ -z "${TRACELESS_PYTHON_BINARY:-}" ] && [ -x /opt/traceless/venv/bin/python ]; then
	export TRACELESS_PYTHON_BINARY=/opt/traceless/venv/bin/python
fi

export TRACELESS_SCANNER_SCRIPT="${TRACELESS_SCANNER_SCRIPT:-$APP_ROOT/python-services/scanner/worker.py}"

# --- Writable paths -------------------------------------------------------
# Releases arrive over ssh and can lose the write bit. Best effort only: the
# process user may not own them, and weeny sets sane defaults.
chmod -R u+w storage bootstrap/cache 2>/dev/null || true
mkdir -p storage/app/public storage/framework/sessions storage/framework/views storage/framework/cache storage/logs 2>/dev/null || true

# --- Database -------------------------------------------------------------
# Migrations are safe to run on every release start (they are additive and
# `--force` is required in production). Never fail the boot because of them.
(cd "$APP_ROOT" && php artisan migrate --force) >>"$LOG_DIR/migrate.log" 2>&1 || log "migrate failed, see storage/logs/migrate.log"

(cd "$APP_ROOT" && php artisan storage:link) >>"$LOG_DIR/migrate.log" 2>&1 || true

# --- Supervised background loops -----------------------------------------
supervise() {
	name="$1"
	shift
	(
		while true; do
			"$@" >>"$LOG_DIR/$name.log" 2>&1
			code=$?
			printf '[%s] exited %s at %s, restarting in 3s\n' \
				"$name" "$code" "$(date -Is)" >>"$LOG_DIR/$name.log"
			sleep 3
		done
	) &
}

ln -sfn "$APP_ROOT" "$HOME/traceless-current" 2>/dev/null || true

cd "$APP_ROOT" || exit 1
supervise queue php artisan queue:work --queue=scans,default --sleep=3 --tries=1 --timeout=610
supervise schedule php artisan schedule:work

# --- Caddy (main process) -------------------------------------------------
exec caddy run --config "$APP_ROOT/deploy/Caddyfile" --adapter caddyfile
