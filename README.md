# TraceLess

Privacy-first digital footprint protection. TraceLess finds the personal
information that is **publicly** discoverable about you, tells you what it
means, gives you a removal path for each record, and watches for new or
reappearing exposure.

The product is passwordless and consent-gated: no account passwords exist, and
no scan runs without a recorded ownership attestation.

> **Status: active development, not production ready.**
> The application runs, the test suites pass, and the core scan-to-removal
> journey works end to end in demo mode. Monetisation, credential monitoring
> and the interface refresh are unfinished. See [Where the project stands](#where-the-project-stands).

---

## Where the project stands

| Area | State |
|---|---|
| Passwordless authentication (email OTP) | **Working** — hashed codes, expiry, lockout, resend cooldown, route throttles |
| Identity profile + recorded consent | **Working** — consent is re-validated before every queued scan |
| Scan pipeline — demo mode | **Working** — deterministic 13-source adapter, labelled as demo |
| Scan pipeline — live mode | **Working with limits** — username only, 24 curated public sources, evidence stored per finding |
| Findings, risk score, reports | **Working** — HTML dossier, signed download URL, per-report token |
| Removal tracking | **Working as manual tracking** — no automated removal engine yet |
| Continuous monitoring | **Partial** — preferences and hourly dispatch exist; live alerts are thin |
| Credential / breach monitoring | **Not connected** — no provider is configured, so live scans never populate it |
| Subscriptions and payments | **Not built** — no payment provider; the paid tier is unreachable in production |
| Real-time updates | **Not wired** — events broadcast to the log; the UI polls |
| Interface refresh | **In progress** — the marketing site and app shell are migrated; ~20 inner screens are not |

### Progress log

| Date | Change |
|---|---|
| 2026-11-14 | Audited the codebase, reconstructed the development timeline, recorded the outstanding work |
| 2026-11-15 | Put the project under version control — tag `baseline-2026-11-14` marks the audited snapshot |
| 2026-11-15 | Removed the local-only `/__preview/dashboard` route that signed in as the seeded demo user |
| 2026-11-15 | Added a fail-closed production guard: the app refuses to boot with `APP_DEBUG`, demo mode, or on-screen OTP codes enabled in production (9 tests) |
| 2026-11-15 | Rebuilt the front-end assets — the previous build predated the last layout edits |
| 2026-11-15 | Made the test suite reproducible (`LOG_CHANNEL=stderr`, documented `VIEW_COMPILED_PATH`) |
| 2026-11-15 | Consolidated documentation into this README and removed the design/export assets |

### Verified right now

- **59 PHP tests** and **14 Python tests** pass.
- All **9 database migrations** apply cleanly.
- `/`, `/login`, `/register`, `/privacy`, `/terms`, `/security` and the `/up`
  health check return 200; `/dashboard` redirects guests to sign-in.

### Next up

1. **Scan failure recovery** — a worker that dies mid-scan currently leaves the
   scan active forever and blocks the account from starting another one.
2. **Honest credential state** — the credentials screen implies "all clear"
   when no breach provider is configured.
3. **Demo labelling everywhere** — demo rows on the dashboard, findings list and
   credentials page are not marked as demo.
4. **Finish the interface refresh** — ~20 screens still use the superseded
   class vocabulary, and light panels remain inside the dark theme.

### Open decisions

- **Commercial model** — free-only launch, manual invoicing, or a payment
  provider before launch.
- **Credential monitoring** — integrate a breach-metadata provider, or remove
  the feature from the product surface until one exists.
- **Hosting** — production host, domain, and a transactional email domain.
- **Scanner topology** — one host running app and worker, or a dedicated
  scanner host.

---

## Repository layout

```
.
├── traceless-app/          Laravel 12 + Livewire 3 application
│   ├── app/                Livewire components, services, models, policies
│   ├── database/           migrations, seeders, factories
│   ├── resources/views/    Blade templates and the design system
│   ├── routes/             web, channels, console
│   └── tests/              PHPUnit feature and unit tests
├── python-services/        scanner worker and its tests (outside the web root)
│   ├── scanner/worker.py   verified public-source checks
│   └── tests/              unittest suite
├── docker/                 nginx, php-fpm and supervisor configuration
├── Dockerfile              multi-stage production image
└── docker-compose.yml      web, queue, scheduler and migrate services
```

## Requirements

- PHP **8.2+** with `pdo_pgsql` (or `pdo_sqlite` for local work)
- Composer
- Node.js **20+** and npm
- Python **3.11+** for live scans
- A queue worker and the scheduler — scans and retention do not run without them

## Local setup

```bash
cd traceless-app
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

Start the three processes the application needs:

```bash
php artisan serve
php artisan queue:work --queue=scans,default
php artisan schedule:work
```

Demo sign-in uses `test@example.com`. The six-digit code is sent by the mailer
configured in `.env` and is never rendered on screen.

## Testing

From `traceless-app/`:

```bash
php artisan test
```

From the repository root:

```bash
python -m unittest discover -s python-services/scanner/tests
```

`phpunit.xml` forces the `stderr` log channel, so the only write requirement is
a compiled-view directory. Where the tree is read-only, redirect it:

```bash
VIEW_COMPILED_PATH=/tmp/traceless-views php artisan test
```

## Demo mode vs live mode

| Mode | Behaviour |
|---|---|
| `TRACELESS_DEMO_MODE=true` (default) | Deterministic demo findings from a local catalogue. Every record is generated, not observed. |
| `TRACELESS_DEMO_MODE=false` | The Python worker issues real HTTP requests to 24 curated public sources and reports a finding only when that source's own existence rule matches. Blocked, timed-out or unmatched sources are recorded as `unknown`, never as findings. |

In live mode only a **public username** is sent to the worker. Emails, phone
numbers and all password material are rejected at the process boundary.
Credential checks are skipped until a breach provider is configured.

## Configuration

Key variables, all documented in `.env.example`:

| Variable | Purpose |
|---|---|
| `TRACELESS_DEMO_MODE` | Switch between generated and verified findings |
| `TRACELESS_OTP_EXPIRY_MINUTES`, `TRACELESS_OTP_MAX_ATTEMPTS`, `TRACELESS_OTP_RESEND_COOLDOWN` | One-time-code policy |
| `TRACELESS_OTP_DEMO_DISPLAY` | Show email-change and account-deletion codes in Settings — **must be false outside local** |
| `TRACELESS_BREACH_PROVIDER` | Breach-metadata provider; empty disables the feature |
| `TRACELESS_PYTHON_BINARY`, `TRACELESS_SCANNER_SCRIPT`, `TRACELESS_PYTHON_TIMEOUT` | Worker invocation |
| `DB_URL`, `DB_LIBPQ_OPTIONS` | Neon/Postgres connection |
| `REDIS_URL`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | Queue, cache and session backends |
| `MAIL_MAILER`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS` | Transactional email |

**Production safety guard.** The application refuses to boot when
`APP_ENV=production` and `APP_DEBUG`, `TRACELESS_DEMO_MODE` or
`TRACELESS_OTP_DEMO_DISPLAY` is still enabled. Start from
`.env.staging.example`, which sets all three correctly.

## Docker

```bash
cp .env.staging.example .env      # fill in real secrets
docker compose build
docker compose run --rm migrate
docker compose up -d web queue scheduler
```

The image runs PHP-FPM and nginx under supervisor and serves `/up` as its
health check. Compose mounts `../python-services` read-only for live scans, so
run it from a checkout that contains both directories.

## Security notes

- **No passwords are stored.** Authentication, email changes and account
  deletion all use short-lived email codes.
- **Consent is a gate.** A scan cannot be queued without a consent record for
  the identity.
- **Public data only.** No authentication bypass, no anti-bot evasion, no
  purchased breach dumps.
- **Ownership checks** scope identities, scans, findings, reports and removals
  to the signed-in user.
- **Reports** use signed, expiring URLs.
- **Secrets** live in `.env`, which is git-ignored; the repository contains only
  `.env.example` and `.env.staging.example`.

## Third-party attribution

Public-source discovery integrates [WhatsMyName](https://github.com/WebBreacher/WhatsMyName)
(CC BY-SA 4.0) and [user-scanner](https://github.com/kaifcodec/user-scanner)
(MIT). TraceLess does not guarantee removal from any third-party service, and
privacy scores are prioritisation aids, not assessments of fact.

## Licence

Proprietary. See the project owner before redistribution.
