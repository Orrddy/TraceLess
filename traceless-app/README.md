# TraceLess

Privacy-first digital footprint protection. TraceLess scans **public** information only, records consent before any scan runs, never requests third-party passwords, and tracks removal work without promising that a source will comply.

This repository contains the Laravel 12 + Livewire 3 application and a Python service boundary for scanner, normalisation and local-AI safety work.

## Project status and planning

TraceLess is in active recovery from a mid-redesign stop. The project-level
documents live in the repository root:

- [Project audit](../TRACELESS_PROJECT_AUDIT.md) — current state, feature matrix, outstanding issues
- [Launch roadmap](../TRACELESS_LAUNCH_ROADMAP.md) — phased plan with acceptance criteria
- [Master TODO](../TRACELESS_MASTER_TODO.md) — the working tracker
- [Decision log](docs/DECISIONS.md) — open decisions that block planned work
- [Deployment runbook](docs/DEPLOYMENT.md) — Neon/Upstash/Resend and container operations

## Passwordless authentication

TraceLess has **no account passwords**. Sign-in and registration use a six-digit email one-time code (OTP):

- Registration: name + email + terms → code emailed → code verified → account created with a verified email and the user signed in.
- Sign-in: email → code emailed → code verified → session regenerated and user signed in.
- Email change: the new address must confirm a code before it becomes the sign-in address.
- Account deletion: requires a code sent to the current sign-in email.
- Codes are stored only as a hash, expire after 10 minutes, are single-use, lock after 5 wrong attempts, and are subject to resend cooldowns and route throttles.
- Login responses are generic so the form cannot be used to enumerate accounts.
- In demo mode the code is displayed on the verification screen and written to the mail log. With `TRACELESS_DEMO_MODE=false` the code is delivered only by the configured mailer.

**Production requirement:** configure a real transactional email provider. TraceLess ships with the Resend transport (`MAIL_MAILER=resend` + `RESEND_API_KEY`) and also works with any SMTP provider. A working mail path is part of authentication, not an optional extra.

## Requirements

- PHP 8.2+ with required extensions (`pdo_pgsql` for Neon)
- Composer
- Node.js 20+ and npm
- SQLite for local development (PostgreSQL/Neon for staging and production)
- Python 3.11+ for the scanner services
- A queue worker and scheduler for scans, monitoring and retention

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan storage:link
```

Start the app:

```bash
php artisan serve
php artisan queue:work --queue=scans,default
php artisan schedule:work
```

Demo sign-in uses `test@example.com`. Because mail uses the log driver locally, the code appears on the verification page (demo mode) and in `storage/logs/laravel.log`.

## Container deployment

The repository ships a production image (`Dockerfile`) that runs PHP-FPM and
nginx under supervisor, plus `docker-compose.yml` with web, queue, scheduler
and one-off migration services. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
for the Neon/Upstash/Resend runbook, verification checklist and security
checklist.

```bash
cp .env.staging.example .env   # fill in secrets
docker compose build
docker compose run --rm migrate
docker compose up -d web queue scheduler
```

## Demo mode vs live mode

| Mode | Behaviour |
|---|---|
| `TRACELESS_DEMO_MODE=true` (default) | Deterministic demo findings from a local catalog. Everything is labelled as demo. OTP codes may be shown locally. |
| `TRACELESS_DEMO_MODE=false` | Uses the Python scanner worker. It issues real HTTP requests to a curated set of public sources and reports a finding only when that source's own existence rule (from the WhatsMyName dataset) matches. Sources that block, time out or do not match are recorded as `unknown`, never as findings. Credential checks are skipped until `TRACELESS_BREACH_PROVIDER` is configured. |

### How live verification works

- Only a **public username** is sent to the worker. Emails, phone numbers and all password material are rejected at the process boundary.
- Each check records the outcome (`found`, `not_found`, `unknown`, `skipped`), HTTP status, checked URL, and a reason such as `source_blocked_request`.
- Verified findings keep an evidence bundle: match rule, HTTP status, final URL, a SHA-256 hash of the response body (never the body itself), elapsed time and timestamp. The finding page shows this under "How this was checked".
- Findings carry `verification_status` (`verified`, `unverified`, `demo`). Previously found records that a later scan cannot confirm are set to `could_not_verify` rather than staying silently current.
- The worker's JSON is treated as untrusted input and normalized by `ScannerResultNormalizer` before anything is persisted.

Remaining gaps before calling this production-grade: a breach-metadata provider, human review of edge cases, and broader source coverage. Do not present live mode as guaranteed or exhaustive.

## Environment

Key variables are documented in `.env.example`. Authentication-specific settings:

```env
TRACELESS_DEMO_MODE=true
TRACELESS_OTP_EXPIRY_MINUTES=10
TRACELESS_OTP_MAX_ATTEMPTS=5
TRACELESS_OTP_RESEND_COOLDOWN=60
TRACELESS_OTP_DEMO_DISPLAY=true   # must be false in production
TRACELESS_BREACH_PROVIDER=         # leave empty until a provider is configured
MAIL_MAILER=log                   # use resend or smtp in production
```

## Managed staging stack (Neon + Upstash + Resend)

The staging foundation runs on free tiers:

1. **Database — Neon Postgres.** Create a project and copy the pooled connection string into `DB_URL`. Set `DB_CONNECTION=pgsql` and `DB_SSLMODE=require`.

   PHP's bundled libpq often cannot send the Neon SNI endpoint, which produces `Endpoint ID is not specified`. Two options:

   - Use a modern libpq/pgsql client, or
   - pass the compute endpoint ID explicitly:

     ```env
     DB_URL=postgresql://USER:PASSWORD@ep-xxxx.c-16.REGION.aws.neon.tech/neondb?sslmode=require
     DB_LIBPQ_OPTIONS=endpoint=ep-xxxx
     ```

   `App\Database\NeonPostgresConnector` forwards `DB_LIBPQ_OPTIONS` into the PDO DSN, because Laravel's default connector does not. Only the endpoint ID is needed; keep the full connection string secret.

2. **Cache/queue/session — Upstash Redis.** Copy the TLS URL into `REDIS_URL`, set `REDIS_CLIENT=predis` (the `phpredis` extension is not required), then enable:

   ```env
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   REDIS_QUEUE_RETRY_AFTER=300
   ```

   Verify with `Redis::connection()->ping()` before switching traffic.

3. **Email — Resend.** Verify a sending domain and set SPF/DKIM/DMARC, then:

   ```env
   MAIL_MAILER=resend
   RESEND_API_KEY=re_...
   MAIL_FROM_ADDRESS=alerts@your-verified-domain.com
   TRACELESS_OTP_DEMO_DISPLAY=false
   ```

   `onboarding@resend.dev` only delivers to the Resend account owner, so it is not usable for real sign-ups.

   **Interim option — SMTP.** Any transactional SMTP provider works. A personal Yahoo account can be used for smoke tests with an app password:

   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mail.yahoo.com
   MAIL_PORT=465
   MAIL_SCHEME=smtps
   MAIL_USERNAME=you@yahoo.com
   MAIL_PASSWORD=app-password
   MAIL_FROM_ADDRESS=you@yahoo.com
   ```

   `MAIL_FROM_ADDRESS` must match the Yahoo address or messages are rejected. Yahoo is unsuitable for production: low daily limits, aggressive anti-abuse throttling, and poor OTP deliverability. Revoke the app password when you move to Resend.

Run migrations and verify:

```bash
php artisan migrate --seed --force
php artisan tinker --execute="echo DB::select('select 1 as ok')[0]->ok;"
```

## Testing

```bash
php artisan test
python -m unittest discover -s python-services/scanner/tests
```

The PHP suite covers registration and sign-in via OTP, generic login responses, wrong-code lockout, expiry, account deletion via OTP, email-change verification, consent gating, the demo and live scan pipelines, untrusted scanner payload normalization, owner scoping and report downloads. The Python suite covers slug normalization, the per-source existence rules, blocked/timeout handling, consent enforcement and evidence generation without touching the network.

## Architecture notes

- `app/Services/OtpService.php` — code issuance, verification, cooldown and hashing.
- `app/Http/Controllers/AuthController.php` — registration, login, shared code verification and resend.
- `app/Livewire/Settings/SettingsPage.php` — email-change and deletion challenges.
- `app/Services/ScanOrchestrator.php` — consent-gated scan pipeline, verification persistence and reconciliation of absent findings.
- `app/Services/DemoScanAdapter.php` — deterministic demo data.
- `app/Services/PythonScannerClient.php` — fixed-argument process boundary around the worker.
- `app/Services/ScannerResultNormalizer.php` — allow-listing and sanitizing of untrusted worker output.
- `python-services/scanner/worker.py` — verified public-source HTTP checks when demo mode is off.
- `python-services/normalizer/normalizer.py` and `python-services/local-ai/safety_engine.py` — exist but are **not yet wired** into the Laravel pipeline.

## Security boundaries

- Public data only; no authentication bypass, no anti-bot evasion, no purchased breach dumps.
- Ownership policies scope identities, scans, findings, reports and removals.
- Reports use signed, expiring URLs.
- Audit logs record account, authentication, consent and removal events.
- Do not deploy with `APP_DEBUG=true`, the demo user, or demo-displayed OTP codes.

## License

Proprietary. See the project owner before redistribution.
