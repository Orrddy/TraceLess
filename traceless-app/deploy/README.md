# Deploying TraceLess to weeny.cloud

A single Linux VPS, no Docker: Caddy terminates HTTP on `:8080`, PHP-FPM runs the
Laravel app, and `run.sh` supervises the queue worker and scheduler. The database
stays on Neon, and the Python scanner ships with the release.

## What is in this folder

| File | Purpose |
| --- | --- |
| `install.sh` | One-time server setup: PHP-FPM, Caddy, Composer, Python venv for deep scans |
| `Caddyfile` | HTTP server on `:8080`, FastCGI to the detected PHP socket |
| `run.sh` | Foreground supervisor (main process), migrations, queue + scheduler loops |
| `push.ps1` | Builds assets locally, stages a release (app + scanner), pushes with weeny |
| `README.md` | This file |

## Prerequisites

- Node.js and `npx` on the machine that pushes.
- Windows: the **OpenSSH Client** optional feature. If weeny fails to create its
  profile with `EPERM`/`EACCES`:
  ```powershell
  $env:WEENY_CONFIG_DIR = Join-Path $env:LOCALAPPDATA 'Weeny'
  ```
- A Neon connection string for the production branch (the app does not host Postgres).

## 1. Log in and create the server

```bash
npx weeny-cloud login     # email + code
npx weeny-cloud create    # ~2 minutes, prints the server name
```

## 2. Prepare the server (once per server)

```bash
npx weeny-cloud ssh 'bash -s' < traceless-app/deploy/install.sh
```

It installs `php-fpm php-cli php-pgsql php-mbstring php-xml php-curl php-zip
php-intl`, Composer, the Caddy static binary, tunes PHP-FPM for a small instance
(`pm=ondemand`, 4 children), and builds `/opt/traceless/venv` with the scanner
dependencies (`httpx`, `curl_cffi`, `rich`, `phonenumbers`, ...).

## 3. Set the environment

`weeny env` values persist across releases. `.env` is **never** uploaded.

```bash
npx weeny-cloud env traceless \
  APP_ENV=production APP_DEBUG=false \
  APP_URL=https://<name>-xxxx.onweeny.com \
  APP_KEY=base64:... \
  DB_CONNECTION=pgsql \
  DB_URL='postgresql://...?sslmode=require' \
  DB_SSLMODE=require \
  DB_LIBPQ_OPTIONS=endpoint=ep-... \
  SESSION_DRIVER=database CACHE_STORE=database QUEUE_CONNECTION=database \
  DB_QUEUE_RETRY_AFTER=720 \
  LOG_CHANNEL=stderr \
  TRUSTED_PROXIES='*' \
  TRACELESS_DEMO_MODE=true \
  TRACELESS_OTP_DEMO_DISPLAY=false \
  TRACELESS_PYTHON_BINARY=/opt/traceless/venv/bin/python \
  TRACELESS_SCANNER_SCRIPT=/home/weeny/traceless-current/python-services/scanner/worker.py
```

Generate the key with `php artisan key:generate --show` locally.

Notes:

- `TRUSTED_PROXIES=*` is required: weeny terminates TLS in front, and without it
  every visitor looks like the proxy, which breaks IP checks and generated URLs.
- `TRACELESS_SCANNER_SCRIPT` must point **inside** the release. The repo default
  is `../python-services/...` because it assumes the app sits in a subfolder;
  on the server the app root *is* the release root.
- **Deep scans** need `TRACELESS_PYTHON_BINARY` pointed at the venv. Without it the
  curated username scan still works (standard library only) and deep scans return
  a clear "deep scanner is not installed" instead of crashing.
- **Start with `TRACELESS_DEMO_MODE=true`** for a safe public preview. Flip to
  `false` once you have run a real scan and are happy with load time.
- **Email OTP**: SMTP is configured and verified (`MAIL_MAILER=smtp`,
  `MAIL_HOST=mail.wuhanrcb.net`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`,
  `MAIL_USERNAME`/`MAIL_PASSWORD`, `MAIL_FROM_ADDRESS=traceless@wuhanrcb.net`).
  Without mail credentials, no one can sign in on a public URL — set those or keep
  demo mode with `TRACELESS_OTP_DEMO_DISPLAY=true` for a private preview only.

## 4. Push

From the repo root:

```powershell
pwsh traceless-app/deploy/push.ps1 -AppName traceless
```

This builds assets locally, stages the app plus `python-services/`, converts the
shell scripts to LF, then runs:

```
npx weeny-cloud push .release traceless \
  --build "composer install --no-dev --optimize-autoloader --no-interaction" \
  --health "curl -fsS http://127.0.0.1:8080/up" \
  -- "bash deploy/run.sh"
```

`run.sh` creates `~/traceless-current` pointing at the current release, runs
migrations, starts the queue worker and scheduler, then Caddy in the foreground.

## 5. Expose it

```bash
npx weeny-cloud ssh 'weeny expose traceless 8080'
```

You get `https://traceless-xxxx.onweeny.com`. Then verify:

```bash
curl -fsS https://traceless-xxxx.onweeny.com/up          # ok
# sign in, run a deep scan, confirm the queue processes it
npx weeny-cloud ssh 'journalctl -u weeny-traceless -f'
```

## Updating the app

Re-run `push.ps1`. A new release directory is created, migrations run on start,
and weeny keeps the previous release for rollback. Environment values and the
database survive.

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| 502 from Caddy | PHP-FPM socket missing. Run `install.sh` again, or check `ls /run/php/` |
| 500 with a guard message despite env being set | PHP-FPM does not inherit weeny's managed env. `run.sh` materialises it into the release `.env` and makes it readable by `www-data`; if you bypass `run.sh`, web requests boot with defaults and the production guard refuses |
| 500 on every page | `APP_KEY` missing in `weeny env` |
| Pages load, scans never move | Queue worker down: `journalctl -u weeny-traceless`, or `storage/logs/queue.log` |
| "deep scanner is not installed" | `TRACELESS_PYTHON_BINARY` not set or venv missing |
| OTP never arrives | Mail env not set, or SMTP rejected; check `journalctl` for `mail` errors |
| Page loads unstyled (HTML only) | Assets emitted as `http://` and blocked as mixed content. Caddy must tell PHP it is secure (`env HTTPS on` in `php_fastcgi`), and `ASSET_URL` should be the https origin |
| 429 from HIBP / AbuseIPDB | Shared server IP rate limits; abuse checks are optional |

## Limits worth knowing

- **Free tier (1 vCPU / 1 GB)** fits PHP-FPM + queue + scheduler, but a deep scan
  competing with traffic will feel slow. The $3 tier is the realistic minimum for
  a public demo.
- **Report downloads** live in `storage/app`, which is per-release. Re-pushing
  replaces the release directory; regenerate reports rather than relying on old
  files, or move storage to object storage later.
- **Videos** (`public/assets/videos`, ~15 MB) are served by Caddy as static files.
- The scanner needs outbound HTTPS to the services it probes.
