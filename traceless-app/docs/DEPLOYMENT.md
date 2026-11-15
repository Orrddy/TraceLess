# Deploying TraceLess

TraceLess is a Laravel 12 application with a queue worker and scheduler. The
managed staging stack is **Neon Postgres + Upstash Redis + Resend**, and the
application ships as a single container that runs PHP-FPM and nginx under
supervisor.

> **Status:** staging infrastructure. The scanner now performs real HTTP
> verification against the WhatsMyName dataset and stores evidence, but breach
> metadata, human review and broad source coverage are not finished. Never
> market removal as guaranteed.

## 1. Prerequisites

- A Neon project (Postgres 16+)
- An Upstash Redis database (TLS enabled)
- A Resend account with a verified sending domain (SPF, DKIM, DMARC), or any
  SMTP provider for interim testing
- Python 3.11+ with the `python-services` directory in the image (live scans)
- A host with Docker Compose, or any host with PHP 8.3, nginx and a process
  supervisor
- A 32-byte `APP_KEY` (`php artisan key:generate --show`)

## 2. Database (Neon)

1. Create the project and database.
2. Copy the connection string into `DB_URL` and set `DB_CONNECTION=pgsql`.
3. Keep `DB_SSLMODE=require`.
4. If PHP reports `Endpoint ID is not specified`, set the compute endpoint ID:

   ```env
   DB_LIBPQ_OPTIONS=endpoint=ep-xxxxxxxxxxxx
   ```

   `App\Database\NeonPostgresConnector` appends this to the PDO DSN. The
   endpoint ID is the first label of the direct host
   (`ep-xxxx.c-16.region.aws.neon.tech`). The value is not a password, but keep
   connection strings out of logs and tickets.
5. Run migrations once:

   ```bash
   php artisan migrate --force
   ```

   Use the pooled host for the application and the unpooled host for long
   migrations if your plan provides both.

## 3. Redis (Upstash)

1. Create the database in the region closest to Neon.
2. Copy the TLS URL (`rediss://default:...@host:6379`) into `REDIS_URL`.
3. Set:

   ```env
   REDIS_CLIENT=predis
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   REDIS_QUEUE_RETRY_AFTER=300
   ```

4. Verify before sending traffic:

   ```bash
   php artisan tinker --execute="echo Redis::connection()->ping();"
   ```

   If the host does not resolve reliably from your machine, connect by IP with
   TLS SNI instead of a URL:

   ```env
   REDIS_URL=
   REDIS_SCHEME=tls
   REDIS_HOST=<resolved-ip>
   REDIS_TLS_SERVER_NAME=your-database.upstash.io
   REDIS_USERNAME=default
   REDIS_PASSWORD=<token>
   ```

   The IP address can change, so use the URL form in deployed environments.

If Redis is unavailable, keep `SESSION_DRIVER=database` / `QUEUE_CONNECTION=database`
until the outage is resolved; switching back requires the `sessions` and `jobs`
tables, which ship with the default migrations.

## 4. Email (Resend)

1. Verify the sending domain and publish SPF, DKIM and DMARC records.
2. Configure:

   ```env
   MAIL_MAILER=resend
   RESEND_API_KEY=re_...
   MAIL_FROM_ADDRESS=alerts@your-verified-domain.com
   ```

3. `onboarding@resend.dev` only delivers to the account owner. It cannot be
   used for real registrations.
4. Send a test OTP through the sign-in form, confirm delivery, then keep
   `TRACELESS_OTP_DEMO_DISPLAY=false`.

### Interim SMTP

Any SMTP provider can stand in while a sending domain is not verified. A
personal Yahoo account works for smoke tests only:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mail.yahoo.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=you@yahoo.com
MAIL_PASSWORD=app-password
MAIL_FROM_ADDRESS=you@yahoo.com
```

Yahoo requires an app password (2-step verification must be on) and the `From`
address must be the Yahoo mailbox. Expect throttling, spam filtering and low
daily limits — do not run staging or production traffic through it.

## 5. Live scanner

Live scans invoke `python-services/scanner/worker.py` as a subprocess. The
worker requires Python 3.11+ and the WhatsMyName dataset at
`python-services/upstream/WhatsMyName/wmn-data.json`.

- Local: the default path (`base_path('../python-services/...')`) is correct.
- Docker: the image installs `python3`, `docker-compose.yml` mounts the repo's
  `python-services` directory at `/opt/traceless/python-services`, and sets
  `TRACELESS_SCANNER_SCRIPT` accordingly.
- Other hosts: set `TRACELESS_SCANNER_SCRIPT` to the absolute path on the
  server and make sure the queue worker can execute it.

The worker only accepts usernames, applies a per-host rate limit, and records
`unknown` for blocked or unmatched sources. Configure
`TRACELESS_BREACH_PROVIDER` before expecting credential findings; until then
the UI states the provider is not configured.

## 6. Application environment

Start from `.env.staging.example` and fill in the placeholders. Production
requirements:

- `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`
- `TRACELESS_DEMO_MODE=false`, `TRACELESS_OTP_DEMO_DISPLAY=false`
- `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, HTTPS only
- a real queue connection and a running worker
- `LOG_CHANNEL=stderr` so container logs reach the platform

### Production safety guard

The application refuses to boot when `APP_ENV=production` and any of the
following is still set to its development value:

| Setting | Why it blocks the boot |
|---|---|
| `APP_DEBUG=true` | Stack traces, configuration values and query bindings leak into responses and logs. |
| `TRACELESS_DEMO_MODE=true` | The scanner returns fabricated findings instead of checking public sources. |
| `TRACELESS_OTP_DEMO_DISPLAY=true` | The six-digit sign-in code is rendered on the verification screen. Because a code is issued for any existing account, this is an account-takeover path. |

The check lives in `app/Support/ProductionSafetyGuard.php` and runs from
`AppServiceProvider::boot()`. It is intentionally a hard failure: a container
that exits on start is easier to notice than a service that quietly
misrepresents its data or hands out sessions.

Consequences to plan for:

- `php artisan` commands (including `config:cache` and `migrate`) will also
  fail until the environment is corrected. Fix the environment; do not disable
  the guard.
- Local, testing and any other non-production environment is unaffected.
- `tests/Feature/ProductionSafetyGuardTest.php` covers both the accepted and
  rejected combinations.

## 7. Docker

Build and start:

```bash
docker compose build
docker compose run --rm migrate
docker compose up -d web queue scheduler
```

The `web` service listens on `:8080` and serves `/up` as a health check.
`queue` consumes `scans,default`; `scheduler` runs `schedule:work`. All three
share a `storage` volume for logs and temporary files.

`docker-compose.yml` mounts `../python-services` as a read-only volume, so run
Compose from a checkout that includes the `python-services` directory next to
`traceless-app`. Without it, demo mode still works but live scans fail with
"Scanner worker is not installed".

The container runs `php artisan optimize` at start-up for the web role only.
Queue and scheduler containers read the runtime environment directly, so
configuration changes require a restart of all services.

## 8. Verification checklist

```bash
curl -fsS https://your-host/up
php artisan about
php artisan migrate:status
php artisan queue:monitor scans:100
```

Then, in the browser:

1. Register with a real address and confirm the OTP arrives.
2. Sign out and sign back in with a new code.
3. Create an identity with the ownership consent checkbox.
4. Run a demo scan and open a finding, report and removal task.
5. Switch to live mode and run a scan for a username you own. Confirm every
   finding shows `Verified` or `Unverified`, never fabricated, and that a
   blocked source is listed as `unknown`.
6. Delete a test account and confirm the OTP challenge.

## 9. Backups and retention

- Neon provides point-in-time restore; confirm the retention window on your
  plan and record it in the runbook.
- Upstash holds cache, sessions and queued jobs only. Do not treat it as the
  system of record.
- Export identity, finding and audit data on a schedule before enabling live
  scans.
- The application has a data-retention purge command; run it from the
  scheduler and verify the audit trail.

## 10. Security checklist

- [ ] No demo user seeded in staging/production (`--seed` is local only).
- [ ] `APP_DEBUG=false`, `APP_ENV=production`.
- [ ] OTP demo display disabled.
- [ ] HTTPS enforced; secure, HTTP-only, SameSite cookies.
- [ ] Neon and Upstash credentials limited to the application and rotated
      after any exposure.
- [ ] Resend key scoped to sending only.
- [ ] Queue worker restarted after deploys.
- [ ] `composer audit` reviewed before each release.
- [ ] Public-data-only policy and consent gate verified in the UI.
