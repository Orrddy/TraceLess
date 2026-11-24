# TraceLess

Privacy-first digital footprint protection. TraceLess finds the personal
information that is **publicly** discoverable about you, tells you what it
means, gives you a removal path for each record, and watches for new or
reappearing exposure.

The product is passwordless and consent-gated: no account passwords exist, and
no scan runs without a recorded ownership attestation.

> **Status: v1.0 public beta — live at https://traceless.orrddy.store.**
> Free during the beta: scans, deep scans and monitoring are open to every
> account. The app runs on Altare as a single container (nginx + PHP-FPM +
> queue + scheduler) with Neon Postgres, the multi-provider IP intelligence
> engine and a Vercel-style light/dark interface. Billing arrives in v2.0.
> Current state and next steps: [PROGRESS.md](PROGRESS.md).

---

## Where the project stands

| Area | State |
|---|---|
| Passwordless authentication (email OTP) | **Working** — hashed codes, expiry, lockout, resend cooldown, route throttles |
| Identity profile + recorded consent | **Working** — consent is re-validated before every queued scan |
| Scan pipeline — demo mode | **Working** — deterministic 13-source adapter, labelled as demo |
| Scan pipeline — live mode | **Working with limits** — username only, every monitored handle, 23 curated public sources, evidence stored per finding |
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
| 2026-11-14 | Audited the codebase, reconstructed the timeline, recorded outstanding work; tagged `baseline-2026-11-14` |
| 2026-11-15 | Production guard, reproducible test suite, honest verification screen, policy enforcement, retention + deletion hardening, live monitoring and alerts, brand identity |
| 2026-11-16 | Deep scans for usernames/emails/phones, self-service tools (password + IP check), data reset, notifications |
| 2026-11-18 | First production deploy (weeny.cloud), real-server deploy constraints fixed |
| 2026-11-19 | Administration panel: roles, users, activity log; operations surface: scan inspector, failed-job console, email delivery log, worker/scheduler heartbeats and `ops:alert` |
| 2026-11-23 | Migrated production to Altare (Docker image, custom domain, HTTPS), stopped the old weeny instance |
| 2026-11-23 | CI pipeline moved to the repo root and made green (tests + GHCR image builds) |
| 2026-11-23 | Vercel-style dashboard and app-wide light/dark brand; landing page rebuilt with product film, marquee, checks slider, rotating Nevera wordmark, typing headline and two word-eating crawlers |
| 2026-11-23 | IP intelligence engine: four providers, 48h cache, single-flight analysis, weighted risk score, public JSON endpoint, honest 503/422 states, enriched IP tool with cache countdown |
| 2026-11-23 | Free v1.0 beta gate (deep scans + monitoring for every account), deep-scan wall-clock budget, cache pruning, queued notifications and mail-failure recording |
| 2026-11-23 | Licensed third-party effects integrated (fluid water-ripple film background, two spider crawlers), a browser-exposed device check card, and the 48h IP-cache countdown |
| 2026-11-23 | Full “We see you” fingerprint panel (device-key + Web APIs) with honest Safari/iOS/Firefox gaps; image build fixed (docker vendor context + composer retries) |
| 2026-11-24+ | Resend sender verification in production, uptime monitoring, backups, legal copy, secret rotation, live deep-scan QA |
### Verified right now

- **212 PHP tests / 923 assertions** and **14 Python tests** pass; Pint is clean.
- All migrations apply cleanly (including the IP intelligence cache and metrics tables).
- `https://traceless.orrddy.store` serves the app over HTTPS with valid certificates:
  `/up`, `/`, `/login` return 200; `/dashboard` redirects guests; admin pages are role-gated.
- The CI workflow on `main` runs the suite and publishes `ghcr.io/orrddy/traceless` on every push.
### Next up (before the 10 December beta)

1. **Production environment** — paste `traceless-app/deploy/altare.env` into the Altare app (Resend sender `TracelessBot <do-not-reply@orrddy.store>`, IP provider keys, beta flags) and redeploy the latest image.
2. **Email deliverability proof** — sign in with Gmail and Outlook; confirm inbox vs spam.
3. **Live QA** — one deep scan end-to-end under the beta gate, dashboard/findings/settings in both themes, mobile widths.
4. **Resilience** — external uptime monitor, Neon restore drill, nightly backup of `/data` reports.
5. **Legal + support** — business name, country, support address, subprocessor list on the privacy page.
6. **Security** — rotate the Altare SFTP password, API tokens and provider keys; decide public vs private repo.
7. **v2.0 scope** — billing (Bachs), waitlist, image/face deep scans.

### Open decisions

- **Launch model** — confirmed: free public beta on 10 December, paid plans in v2.0.
- **Repo visibility** — currently public; decide whether to make it private before launch.
- **Monitoring plan gate** — free during beta via `TRACELESS_BETA=true`; v2.0 restores plan checks.
- **Second provider fallback for mail** — Resend primary; keep the DirectAdmin relay as a documented fallback.
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
| `TRACELESS_DEMO_MODE=false` | The Python worker issues real HTTP requests to 23 curated public sources and reports a finding only when that source's own existence rule matches. Blocked, timed-out or unmatched sources are recorded as `unknown`, never as findings. Every monitored username is checked, up to `TRACELESS_MAX_USERNAMES`. |

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

## Administration

The admin area lives at `/admin` and is only reachable by accounts whose
`users.role` is `admin` or `super_admin`.

| Page | Purpose |
| --- | --- |
| `/admin` | Live operational counts (users, scans by status, findings, removals, alerts, plans), worker/scheduler heartbeats, queue and email health |
| `/admin/users` | Search, role filter, suspend/reactivate, role changes (super admin only) |
| `/admin/activity` | The audit log with search, action and date filters |
| `/admin/scans/{id}` | Per-scan forensics: every source outcome and block reason, findings, the failure message, retry and cancel |
| `/admin/failed-jobs` | Dead-letter console: exception excerpt, retry, delete, retry all |
| `/admin/email` | Delivery log for every outbound message, with transport errors |

Silent failures are covered twice over: the queue worker and scheduler write
heartbeats shown on the dashboard, and `ops:alert` (scheduled every ten minutes)
emails administrators when the worker goes quiet, failed jobs appear, or outgoing
mail starts failing.

**Provisioning is CLI-only** — there is no web route that can grant admin, so a
compromised client session cannot escalate:

```bash
php artisan admin:promote owner@example.com --role=super_admin   # or admin
php artisan admin:revoke owner@example.com                       # refuses to remove the last admin
```

Rules enforced in the component, not just the UI: nobody can suspend or demote
themselves, an `admin` cannot act on a `super_admin`, role changes require
`super_admin`, and suspended accounts lose all authenticated access (`active`
middleware). Two bypasses were closed during the security audit: Livewire actions
skip route middleware, so each mutating method re-checks the actor; and admin
components can be instantiated directly through `/livewire/update`, so each one
guards in `mount()` as well.

## Third-party attribution

Public-source discovery integrates [WhatsMyName](https://github.com/WebBreacher/WhatsMyName)
(CC BY-SA 4.0) and [user-scanner](https://github.com/kaifcodec/user-scanner)
(MIT). TraceLess does not guarantee removal from any third-party service, and
privacy scores are prioritisation aids, not assessments of fact.

## Licence

Proprietary. See the project owner before redistribution.
---

## Scan console assets

The scan progress screen renders a rotating 3D globe with **Three.js** (MIT) using the
NASA **Blue Marble** equirectangular texture (public domain, via Wikimedia Commons).
The countdown is driven by server timestamps (queue time + the expected deep-scan
window), not the client clock.

## Uptime monitoring

`.github/workflows/uptime.yml` probes `https://traceless.orrddy.store/up` every five
minutes from GitHub's infrastructure and emails an alert through Resend when the status
is not 2xx/3xx. Add these repository secrets for alerts (Settings → Secrets and
variables → Actions):

- `RESEND_API_KEY` — same Resend key used by the app
- `ALERT_EMAIL` — where alerts should go (e.g. `orrddy@proton.me`)

Optional repository variable: `PROD_URL` (defaults to `https://traceless.orrddy.store`).
For SMS/phone escalation, point an UptimeRobot or Better Stack monitor at the same `/up`
endpoint and use the same alert address.

## Third-party effects (commercial licences)

Two interactive effects on the landing page are adapted from effect bundles whose
commercial-use licences were purchased from their authors' official distribution:

- **Water ripple distortion** — adapted from [Mouse Effects](https://github.com/cortiz2894/mouse-effects)
  by Christian Ortiz. The React Three Fiber implementation was ported to dependency-free
  WebGL2 (fluid ping-pong simulation, chromatic aberration, specular highlights) and now
  samples the product film as its texture. See `resources/js/vendor/water-ripple.js`.
- **Web Crawlers** — adapted from [Web Crawlers](https://github.com/cyohei9907/web-crawlers)
  by cyohei9907. The page-overlay spider (closed shadow root, no page mutation) was wrapped
  in a factory so the landing page runs two small crawlers. See
  `resources/js/vendor/web-crawler-spider.js`.

Credit is preserved in the source headers. The typewriter, device probe, film composition
and page layout are original TraceLess work.
## Browser fingerprint panel

The landing page's "👀 We see you" panel reads only what the browser itself exposes.
Device detection, OS, browser/engine/vendor, battery, network estimates and a canvas
fingerprint come from the MIT-licensed [`@marufme/device-key`](https://github.com/maruf-me/device-key)
package; GPU/renderer, storage quota, media capabilities and the local device id are
read directly from Web APIs. Nothing is transmitted or stored.

Safari/iOS and Firefox deliberately withhold parts of this surface. The panel prints
"Not exposed" with a browser-specific explanation instead of guessing:

- **iOS/iPadOS**: no battery API, no device memory, no network details, no user-agent
  client hints, and device model hidden. iPadOS is detected from the desktop-class
  Safari UA (`MacIntel` + touch) and labelled as iPadOS.
- **macOS Safari**: UA client hints, device memory and network information are hidden;
  WebGL vendor/renderer are masked. Hard-window model is not exposed.
- **Firefox**: UA client hints and device memory withheld; canvas/WebGL signatures can
  be randomised per session.