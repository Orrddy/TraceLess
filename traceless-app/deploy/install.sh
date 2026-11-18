#!/usr/bin/env bash
# One-time server preparation for TraceLess on weeny.cloud.
#
# Run as root, once per server (not per release):
#   npx weeny-cloud ssh 'bash -s' < traceless-app/deploy/install.sh
#
# Installs PHP-FPM, Caddy, Composer and the Python venv used by deep scans.
# Nothing here writes into the release tree; releases are pushed separately.

set -euo pipefail

export DEBIAN_FRONTEND=noninteractive

log() { printf '\n== %s\n' "$*"; }

log "apt packages"
apt-get update -qq
apt-get install -y -qq --no-install-recommends \
	php-fpm php-cli php-pgsql php-mbstring php-xml php-curl php-zip php-intl \
	unzip git curl ca-certificates python3 python3-venv python3-pip

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
log "php $PHP_VERSION"

log "composer"
if ! command -v composer >/dev/null 2>&1; then
	curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

log "caddy (static binary, no apt repo needed)"
if ! command -v caddy >/dev/null 2>&1; then
	case "$(uname -m)" in
		x86_64) caddy_arch=amd64 ;;
		aarch64 | arm64) caddy_arch=arm64 ;;
		*) echo "unsupported architecture: $(uname -m)" >&2; exit 1 ;;
	esac
	curl -fsSL "https://github.com/caddyserver/caddy/releases/download/v2.8.4/caddy_2.8.4_linux_${caddy_arch}.tar.gz" \
		| tar -xz -C /usr/local/bin caddy
	chmod +x /usr/local/bin/caddy
fi

log "tune php-fpm for a small instance"
mkdir -p /run/php
POOL="/etc/php/$PHP_VERSION/fpm/pool.d/www.conf"
if [ -f "$POOL" ]; then
	sed -i 's/^pm = .*/pm = ondemand/' "$POOL"
	sed -i 's/^pm.max_children = .*/pm.max_children = 4/' "$POOL"
	sed -i 's/^pm.process_idle_timeout = .*/pm.process_idle_timeout = 10s/' "$POOL"
	sed -i 's/^;pm.max_requests = .*/pm.max_requests = 500/' "$POOL"
fi

# Composer and artisan need more than the CLI default on a 1 GB box.
CLI_INI="/etc/php/$PHP_VERSION/cli/php.ini"
if [ -f "$CLI_INI" ]; then
	sed -i 's/^memory_limit = .*/memory_limit = 512M/' "$CLI_INI"
fi

if command -v systemctl >/dev/null 2>&1; then
	systemctl enable --now "php${PHP_VERSION}-fpm" >/dev/null 2>&1 || true
fi
service "php${PHP_VERSION}-fpm" start >/dev/null 2>&1 || true

log "python venv for deep scans"
python3 -m venv /opt/traceless/venv
/opt/traceless/venv/bin/pip install --quiet --no-cache-dir --upgrade pip
/opt/traceless/venv/bin/pip install --quiet --no-cache-dir \
	"httpx[http2]>=0.27,<0.29" \
	"socksio>=1.0,<2" \
	"colorama>=0.4,<1" \
	"rich>=13" \
	"curl_cffi>=0.7,<1" \
	"phonenumbers>=8.13"

log "done"
echo "php-fpm: $(ls /run/php/*.sock 2>/dev/null | head -n1 || echo 'socket not found yet')"
echo "python : $(/opt/traceless/venv/bin/python --version)"
echo
echo "Next: set the environment with 'npx weeny-cloud env traceless KEY=value ...', then push."
