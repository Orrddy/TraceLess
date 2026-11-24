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

# systemd units often run without HOME; `set -u` would otherwise abort here.
export HOME="${HOME:-/root}"

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export APP_ROOT
export PATH="/usr/local/bin:/usr/bin:/bin:$PATH"

LOG_DIR="$APP_ROOT/storage/logs"
mkdir -p "$LOG_DIR"

log() { printf '[run] %s\n' "$*" >&2; }

# --- Environment for PHP-FPM ---------------------------------------------
# weeny injects the managed env into this start command only. PHP-FPM is its own
# service and does not inherit it, so without a .env file web requests boot with
# config defaults (demo mode on) and the production guard refuses to serve.
# Writing the release .env here is what makes the web tier see the same config.
materialise_env() {
	[ -n "${APP_KEY:-}" ] || { log "APP_KEY missing; relying on an existing .env"; return 0; }

	vars="APP_NAME APP_ENV APP_DEBUG APP_KEY APP_URL ASSET_URL LOG_CHANNEL LOG_LEVEL
	DB_CONNECTION DB_URL DB_SSLMODE DB_LIBPQ_OPTIONS DB_QUEUE_RETRY_AFTER
	SESSION_DRIVER SESSION_SECURE_COOKIE CACHE_STORE QUEUE_CONNECTION
	TRUSTED_PROXIES TRACELESS_DEMO_MODE TRACELESS_OTP_DEMO_DISPLAY
	TRACELESS_PYTHON_BINARY TRACELESS_SCANNER_SCRIPT TRACELESS_MAX_USERNAMES
	TRACELESS_MAX_EMAILS TRACELESS_MAX_PHONES TRACELESS_BREACH_PROVIDER
	MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_SCHEME MAIL_USERNAME MAIL_PASSWORD
	MAIL_FROM_ADDRESS MAIL_FROM_NAME RESEND_KEY"

	umask 077
	: >"$APP_ROOT/.env"
	for name in $vars; do
		eval "value=\${$name:-}"
		[ -n "$value" ] || continue
		escaped=$(printf '%s' "$value" | sed "s/'/\\\\'/g")
		printf "%s='%s'\n" "$name" "$escaped" >>"$APP_ROOT/.env"
	done
	chmod 640 "$APP_ROOT/.env"
	# PHP-FPM runs as www-data; a root-only .env would be invisible to the web tier.
	chown root:www-data "$APP_ROOT/.env" 2>/dev/null || true
	log "materialised $(wc -l <"$APP_ROOT/.env") env entries for php-fpm"
}

materialise_env

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
mkdir -p storage/app/public storage/framework/sessions storage/framework/views storage/framework/cache storage/logs 2>/dev/null || true
chown -R root:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R g+rwX storage bootstrap/cache 2>/dev/null || true

# --- Database -------------------------------------------------------------
# Migrations are safe to run on every release start (they are additive and
# `--force` is required in production). Never fail the boot because of them, but
# surface the reason in the app output: a failed first migration means every page
# will 500, so the cause must be visible in `journalctl`/deploy output.
if ! (cd "$APP_ROOT" && php artisan migrate --force) >>"$LOG_DIR/migrate.log" 2>&1; then
	log "migrate failed:"
	tail -n 30 "$LOG_DIR/migrate.log" >&2 || true
fi

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
supervise queue php artisan queue:work --queue=scans,default --sleep=3 --tries=1 --timeout=7800
supervise schedule php artisan schedule:work

# --- Caddy (main process) -------------------------------------------------
exec caddy run --config "$APP_ROOT/deploy/Caddyfile" --adapter caddyfile
