# TraceLess — Project Status Audit

**Audit date:** 2026-11-14 02:24 → 2026-11-15 21:48 (Africa/Lagos)
**Code snapshot audited:** frozen at **2026-11-14 02:42** (last write: `traceless-app/resources/views/layouts/app.blade.php`)
**Auditor role:** incoming architect / full-stack engineer / QA / launch strategist
**Mode:** read-only investigation. No application code was modified, no dependency installed, no migration run, no Git operation performed.

Every claim below is tagged:

- **[C] CONFIRMED** — read directly in the codebase, or produced by a command whose output I captured.
- **[I] INFERRED** — follows from how the framework/runtime works, but was not observed end-to-end.
- **[U] UNVERIFIED** — could not be checked in this environment; the limitation is stated.

Section J lists exactly what could and could not be executed.

---

## A. Executive summary

### What TraceLess is

TraceLess is a privacy-first digital-footprint protection SaaS. It helps one person discover what about them is publicly discoverable, understand the risk, act on each exposure, and keep watching for new or reappearing exposure.

The product is implemented as a **Laravel 12 + Livewire 3 monolith** (`traceless-app/`) with a **Python scanning worker** kept outside the web root (`python-services/`). It is a genuinely server-rendered application with working authentication, consent-gated scanning, finding persistence, removal tracking, monitoring preferences, signed report delivery and account self-service — not a mockup.

### What has actually been built

A real, working product skeleton with a surprisingly strong security-conscious core:

- **Passwordless email OTP authentication** — six-digit codes, bcrypt-hashed, 10-minute expiry, single-use, 5-attempt lockout, resend cooldown, route throttles, generic login responses to prevent enumeration, session regeneration, email-change and account-deletion challenges. This is the most complete part of the system.
- **Consent-gated identity profiles** with an ownership attestation recorded in `consent_records`, plus public identifiers (usernames, emails, phones, URLs).
- **Two-mode scan pipeline** orchestrated by `ScanOrchestrator`: a deterministic **demo adapter** (13 fabricated sources, clearly flagged `demo_mode`), and a **live mode** that shells out to a Python worker which makes real HTTP checks against 24 curated sites from the WhatsMyName dataset and stores a verification evidence bundle (match rule, HTTP status, final URL, SHA-256 of the body).
- **Findings, risk scoring, removal tasks, alerts, monitoring subscriptions, reports, audit logs, notifications and a data-retention command** all exist with real migrations and real relationships.
- **Signed report downloads** (signed route + per-report token + ownership check + audit + download counter).
- **A finished visual identity** for the marketing surface and the app shell: obsidian/cyan "private intelligence terminal" world, full-viewport hero video, processing video, telemetry strips, ASCII classification stamps. This is a complete redesign that replaces the original Stitch light-glass design.

### Current overall state

**Roughly 65–70 % of a launchable product, with a hard stop in the middle of a visual redesign and several product-level gaps that are decisions, not bugs.**

Three things define the current state:

1. **The engineering core is sound and tested.** All **50 PHP tests pass (253 assertions)** and all **14 Python tests pass** when the app can write to `storage/` [C]. Migrations are applied and in sync with the schema. The pipeline is covered by feature tests, including owner-scoping and untrusted-payload normalisation.
2. **The UI/UX redesign is unfinished and was cut off mid-pass.** Of 34 Blade views, **3 are fully in the new visual world, 9 are mixed, and 20 still use the superseded class vocabulary** [C]. A compatibility shim in `app.css` keeps those 20 legible, but ~30 hardcoded light surfaces (`bg-white/40`–`bg-white/95`, solid `bg-white`) remain inside the dark world, which produces light panels behind near-white text on 11 screens [C].
3. **Several core promises are not implemented, only scaffolded.** There is **no payment provider** (Deep Scan cannot be bought in production), **no breach-data provider** (credential monitoring only shows demo data yet the UI says "all clear"), **no automated removal**, and **no consumed real-time channel** despite six broadcast events.

### The single most important risk

**There is no version control anywhere.** `C:\My Web Sites\DIGITAL FOOTPRINT` and `traceless-app` both lack `.git` [C]. The only Git repositories in the tree are the vendored upstream clones under `python-services/upstream/`. Everything else — 5,104 lines of PHP, 3,397 lines of Blade, 9 migrations, the Python worker, the docs, the env templates — exists as unversioned files on one disk.

This matters more than any individual bug because:

- there is no rollback if the next change breaks something;
- there is no diff/review trail for the ~20 files that a second AI agent rewrote during this very audit (see §F);
- the shipped CI workflow (`.github/workflows/ci.yml`) can never run;
- any "finish the product systematically" plan is unenforceable without a baseline.

**Verdict: not launch-ready, but much closer than a typical mid-project codebase. The next move is not to write features — it is to freeze a baseline, remove three production hazards, and finish the half-applied redesign.**

---

## B. Technology stack

### Runtime and frameworks

| Layer | Technology | Version / evidence |
|---|---|---|
| Language | PHP | `^8.2` required (`composer.json`); local 8.2.12 (XAMPP), CI and Docker use 8.3 [C] |
| Framework | Laravel | `12.68` pinned (`composer.json`) [C] |
| Component layer | Livewire | `^3.5`, 15 components [C] |
| Templating | Blade | 34 views [C] |
| Styling | Tailwind CSS | `^3.4.13` + `@tailwindcss/forms`, custom token layer in `resources/css/app.css` (523 lines) [C] |
| Client JS | Alpine.js, Vite, laravel-vite-plugin | Alpine `^3.17`, Vite `^6`, plugin `^1.2` [C] |
| WebSockets | Laravel Reverb | `^1.12` installed but **not running** (`BROADCAST_CONNECTION=log`) [C] |
| Queue / cache | Predis | `^3.7`; Upstash-compatible config; local `.env` uses `database` queue + `file` cache [C] |
| Mail | Resend PHP SDK | `^1.18`; local `.env` uses the `log` mailer [C] |
| Database | PostgreSQL 16+ (Neon) | SQLite for tests (`phpunit.xml` → `:memory:`) [C] |
| Tests | PHPUnit | `^11.0.1` (50 PHP tests) + Python `unittest` (14 tests) [C] |
| Scanner | Python | 3.11+ required (local 3.14.3); stdlib-only worker using `urllib` and `concurrent.futures` [C] |
| Containers | Docker multi-stage | `node:20-alpine` → `composer:2` → `php:8.3-fpm-alpine`; compose services web/queue/scheduler/migrate [C] |

### Application dependencies worth noting

- **No payment SDK** (no Cashier, Stripe, Paddle, Mollie) [C].
- **No breach-metadata SDK** — `TRACELESS_BREACH_PROVIDER` is a bare string read by one UI component and never used to call anything [C].
- **No PDF library** — reports are self-contained HTML dossiers downloaded as `.html` [C].
- **No admin/back-office package** [C].
- **No queue-supervision package** beyond Docker Compose services [C].

### Vendored third-party data and code

| Item | Purpose | Licence |
|---|---|---|
| `python-services/upstream/WhatsMyName` (incl. 260 KB `wmn-data.json`) | Existence rules for 24 curations | CC BY-SA 4.0 [C] |
| `python-services/upstream/user-scanner` | Reference tool, **not imported** | MIT [C] |
| `stitch_smart_api_key_validator.zip`, `stitch_ui/` | Stitch design exports (HTML + PNG mockups) | design assets [C] |

Both vendored projects contain their own `.git` directories, which will confuse any future `git add .` at the project root [C] → see issue **D-01**.

---

## C. Project architecture

### Repository layout

```
C:\My Web Sites\DIGITAL FOOTPRINT\
├── traceless-app\                 ← the Laravel application (its own deployable unit)
├── python-services\               ← scan worker + normaliser + local-AI (outside the web root)
│   ├── scanner\worker.py          ← the only Python the app invokes
│   ├── normalizer\normalizer.py   ← NOT wired
│   ├── local-ai\safety_engine.py  ← NOT wired
│   └── upstream\                  ← vendored WhatsMyName + user-scanner (each with .git)
├── .stitch\DESIGN.md              ← original light-glass design system (superseded)
├── stitch_ui\                     ← Stitch screen exports
├── stitch_smart_api_key_validator.zip
├── tl.md                          ← original build brief
├── new uiux.md                    ← redesign brief (newest instruction document)
├── me.mp4, bg neon.mp4            ← owner-provided video masters (copies in public/assets/videos)
└── (no .git anywhere)
```

The two-video duplication is byte-identical: `me.mp4` ≡ `public/assets/videos/hero-intro.mp4`, `bg neon.mp4` ≡ `scanner-loop.mp4` (SHA-256 match) [C].

### Request path

1. **Web tier** — nginx → PHP-FPM → `public/index.php` → Laravel 12 kernel.
2. **Routing** — `routes/web.php` (89 lines originally, 107 after the 02:36 addition): public marketing routes, guest auth routes, and an `auth`-middleware "command centre" group covering dashboard, identity, scans, findings, credentials, removals, monitoring/alerts, reports, settings, export [C].
3. **Page rendering** — each route maps to either a Blade view (`/`, `/privacy`, `/terms`, `/security`) or a **full-page Livewire component** that renders inside `layouts.app`.
4. **Interaction** — Livewire handles every mutation via its own signed payload; there are only four non-Livewire controllers: auth, report download, account export, base controller [C].
5. **Ownership** — each Livewire component re-checks `user_id === auth()->id()` with `abort_unless`; the five Policies are **defined but never called** (see **S-04**) [C].

### Scan pipeline (the product's core)

```
Livewire ScanCreate / Dashboard
   └─ ScanOrchestrator::queueScan()            consent re-validated here (hard gate)
        ├─ creates Scan (status=queued, demo_mode frozen at enqueue)
        └─ RunScanJob::dispatch()->onQueue('scans')
              └─ ScanOrchestrator::executeScan()
                   1. stage: initializing            (3 %)
                   2. stage: checking_sources        (10 %)
                        ├─ demo  → DemoScanAdapter::findingsFor()   13 catalog sources, seeded
                        └─ live  → PythonScannerClient::scanReport() → worker.py (HTTP)
                                  → ScannerResultNormalizer (allow-list, strip tags, http(s) only)
                   3. per-source loop: ScanSource rows, Finding upserts, RemovalTask creation,
                      Alert + notification for critical, events emitted, progress 10→62 %
                   4. reconcileAbsentFindings()      live only: unconfirmed → could_not_verify
                   5. stage: normalizing_results     (70 %)  ← label only, no dedupe
                   6. stage: calculating_risk        (84 %)  RiskScoringService
                   7. stage: generating_report       (93 %)  ReportService + ReportGenerated
                   8. status=completed (100 %), ScanCompleted, ScanCompletedNotification
```

Decoupling facts worth knowing:

- The scan job runs on the `scans` queue with **`$tries = 1`, `$timeout = 180`, and no `failed()` handler** [C]. A killed worker leaves the scan mid-stage; `IdentityProfile::activeScan()` treats any non-terminal status as active, so the user is then blocked from starting another scan [C] → **C-02**.
- Progress is **database-backed and polled** by the browser every 1.5 s (`wire:poll.1500ms`), not pushed [C].
- Demo and live paths differ in: source list, credential insertion, verification defaults, reconciliation, and whether monitoring alerts fire [C].

### Database

9 migrations, all applied on the configured Postgres database (batches 1–2) [C]:

`users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`, `identity_profiles`, `public_identifiers`, `scans`, `scan_sources`, `findings`, `credential_exposures`, `removal_tasks`, `removal_instructions`, `monitoring_subscriptions`, `alerts`, `consent_records`, `audit_logs`, `reports`, `subscriptions`, `billing_records`, `notifications`, `email_otps`.

Integrity gaps: no unique constraint on `monitoring_subscriptions.identity_profile_id`, `removal_tasks.finding_id`, `subscriptions(user_id, plan)`, or `public_identifiers(profile, type, value)`; `findings.scan_id` is `nullOnDelete`; **no encrypted casts exist anywhere** despite UI claims of encryption [C] → **S-05**, **F-10**.

### External services actually integrated

| Service | State |
|---|---|
| Neon Postgres | **Live** — local `.env` points at the `production` branch; 9 migrations applied [C] |
| Upstash Redis | Configured but **not enabled** locally (`REDIS_URL` empty, queue/cache/session are database/file) [C] |
| Resend | Package installed, key present on disk, but `MAIL_MAILER=log` → **no live email** locally [C] |
| Reverb | Installed, configured, **never started**; events go to the log channel [C] |
| WhatsMyName dataset | **Live** — used by the worker for real matching [C] |
| Breach provider | **Absent** [C] |
| Payment provider | **Absent** [C] |
| Object storage (R2/S3) | Config present, credentials empty, unused [C] |

### Build and run

- Front-end assets are built with Vite into `public/build` (git-ignored). Last build: **02:36**, six minutes *before* the last two layout edits [C] → **U-12**.
- Local run needs three processes: `php artisan serve`, `php artisan queue:work --queue=scans,default`, `php artisan schedule:work` (documented in the README) [C].
- The scheduler is registered in `routes/console.php`: hourly monitoring dispatch + daily retention purge [C].

---

## D. Documentation review

### Documents that exist and were read in full

| Document | Size | What it is | Still accurate? |
|---|---|---|---|
| `traceless-app/README.md` | 190 lines | Setup, passwordless auth, demo vs live mode, env matrix, staging stack, testing, architecture notes, security boundaries | **Mostly accurate.** Overstates two things: "ownership policies scope…" (policies are never invoked) and "reports use signed, expiring URLs" (true for the page link, false for `Report::downloadUrl()`) [C] |
| `traceless-app/docs/DEPLOYMENT.md` | 215 lines | Neon + Upstash + Resend runbook, Docker, verification and security checklists | **Accurate about infrastructure, wrong about one thing:** it claims "until then the UI states the provider is not configured" for breach data — the current credentials view no longer says that [C] |
| `traceless-app/PRODUCT.md` | 70 lines | Impeccable product schema: users, positioning, capabilities, constraints, brand commitments, principles | **Accurate and useful.** Explicitly forbids fabricating testimonials/benchmarks and requires demo labelling [C] |
| `traceless-app/.impeccable/briefs/home.md` | 33 lines | Surface brief for the redesign pass: thesis, own-world tokens, story, first viewport, finish definition | **Describes the pass that was cut off.** Its own finish rule ("ends with the finish review, the verdict, DESIGN.md") was not met [C] |
| `.stitch/DESIGN.md` | 309 lines | Original design system: midnight canvas, blue glass, iPhone materials | **Superseded and contradicted** by `new uiux.md` (obsidian/cyan). Still referenced by `tl.md` as the source of truth [C] |
| `new uiux.md` (project root) | 421 lines | The newest instruction set: install Impeccable, then a full redesign with two videos and a terminal aesthetic | **Partially executed.** See §D.3 |
| `tl.md` (project root) | 579 lines | The original build brief: stack, routes, models, features, security, testing, docs | **Largely executed**, with known gaps (Deep Scan, credentials, automation, real-time) [C] |

### Documents that do **not** exist

No `TODO.md`, `CHANGELOG.md`, `ROADMAP.md`, `CONTRIBUTING.md`, `ARCHITECTURE.md`, `API.md`, ADRs, or issue tracker [C]. The only forward-looking artefacts are the README's "Remaining gaps" sentence and the Impeccable brief [C].

This absence is itself a finding: **there is no written record of decisions, sequencing or known defects**, which is why this audit had to reconstruct the state from code and timestamps.

### D.1 Important decisions recorded in the docs

1. **Passwordless only.** "TraceLess has no account passwords" — and the schema was migrated to drop password storage [C].
2. **Public data only, username-only live scanning.** Emails/phones rejected at the process boundary [C].
3. **Demo mode is a first-class mode**, deterministically seeded and labelled [C].
4. **Verification over volume.** A finding requires the source's own existence rule to fire; blocked/unmatched sources are `unknown`, never findings [C].
5. **Removal is guided, never guaranteed.** Repeated in PRODUCT.md, README, landing and scan consent copy [C].
6. **Managed stack on free tiers**: Neon + Upstash + Resend, containerised as one image with three roles [C].
7. **Design language**: obsidian/cyan terminal world (newest), with a *transition* strategy — a legacy shim so untouched screens stay legible [C].

### D.2 Documentation vs. code discrepancies

| # | Document claim | Reality | Severity |
|---|---|---|---|
| 1 | README: "Ownership policies scope identities, scans, findings, reports and removals." | Five policies exist; **zero** `authorize()`/`Gate`/`can()` calls in `app/`. Ownership is enforced inline instead, and correctly — but the stated mechanism is not used | Medium |
| 2 | README: "Reports use signed, expiring URLs." | The Reports page builds a proper signed URL; `Report::downloadUrl()` builds an **unsigned** one and is used in the `ReportGenerated` broadcast payload | Medium |
| 3 | DEPLOYMENT.md: "until then the UI states the provider is not configured" | The credentials empty state now reads "All monitored identifiers are clear of active public credential breaches" with no provider notice | **High** (misrepresentation) |
| 4 | `pages/security.blade.php`: "identifiers validated and encrypted" | No encrypted casts, no `Crypt::`, no at-rest encryption | **High** (false claim) |
| 5 | `layouts/app.blade.php`: "STATUS: ENCRYPTED // TR-782" | Same as above; also "TR-782" is a hardcoded decorative ID | High |
| 6 | `pages/privacy.blade.php`: consent can be withdrawn "by deleting the identity" | There is no identity-delete feature; `ConsentService::revoke()` has no caller | Medium |
| 7 | `settings-page.blade.php`: "Checkout is delegated to a PCI-compliant provider" + "Compare plans" → `#pricing` | No provider; `#pricing` does not exist on the landing page | **High** (dead link + false claim) |
| 8 | `tl.md` requires a "Cancel scan button" on the running scan | `ScanProgress::cancel()` exists; the redesigned view renders **no** cancel control | Medium |
| 9 | PRODUCT.md: "demo data … always labelled" | Dashboard, findings list and credentials present demo rows with **no** demo badge | **High** |
| 10 | `README.md`: "A working mail path is part of authentication" | True as code; locally `MAIL_MAILER=log`, so OTP mail is only written to disk | Expected in local |

### D.3 How much of `new uiux.md` was executed

| Requirement | State |
|---|---|
| Install the real Impeccable skill and verify `SKILL.md` | **Done, but for a different agent.** Impeccable **v4.9.1** is installed at `C:\Users\Orrddy\.gemini\config\skills\impeccable\SKILL.md` (Gemini CLI), not in this DSH session's skill catalog [C] |
| Run `/impeccable init` | **Done** — `PRODUCT.md` carries `<!-- impeccable:product-schema 1 -->` [C] |
| Run `/impeccable craft` | **Started** — `.impeccable/briefs/home.md` exists; no finish review, no verdict [C] |
| Produce/refresh `DESIGN.md` | **Not done** — no Impeccable `DESIGN.md`; only the older `.stitch/DESIGN.md` [C] |
| Full-screen hero video with sound opt-in, skip, no replay | **Done** — `landing.blade.php` has `autoplay loop playsinline muted`, `Enable Sound`, `Skip Intro`, `localStorage` intro flag [C] |
| Processing video during scans | **Done** — `scan-progress.blade.php` uses `scanner-loop.mp4` under a dark overlay [C] |
| Redesign homepage | **Done** [C] |
| Redesign authenticated dashboard | **Done** [C] |
| Redesign *remaining* application screens | **Not done** — 20 of 34 views untouched by the new vocabulary [C] |
| Responsive + a11y + reduced motion | **Partial** — `prefers-reduced-motion` block exists; some new-world pages carry legacy light surfaces; no automated a11y test [C] |
| "Never hardcode Windows paths" | **Satisfied** — videos were copied into `public/assets/videos/` [C] |

---

## E. Feature implementation matrix

Legend: **Complete** · **Partially implemented** · **UI only** · **Backend only** · **Not started** · **Broken** · **Needs verification**

| Feature | Frontend | Backend | Testing | Status |
|---|---|---|---|---|
| **Authentication** (register, login, OTP verify, logout, email change, deletion challenge) | Complete — new two-panel auth shell, labelled passwordless, inline errors | Complete — `AuthController`, `OtpService` (hash, expiry, lockout, cooldown), throttles, session regeneration | Complete — 9 feature tests incl. lockout, expiry, generic login response | **Complete**, with one configuration-time hazard (**S-01**) |
| **Identity profile** | Partially implemented — create page lost `previous_names`, `aliases`, email/phone/URL chips; "remove username" calls a non-existent method | Complete-ish — create/edit/add identifiers, consent record, score recompute; **no delete, no consent withdrawal** | Good — consent gating and creation covered | **Partially implemented** |
| **Digital footprint scan — demo** | Complete — create form, progress screen, source/finding counts | Complete — deterministic adapter, 13 sources, labelled demo | Complete — end-to-end demo pipeline test | **Complete** (demo only) |
| **Digital footprint scan — live** | Partially implemented — progress screen no longer shows the source list or findings feed | Partially implemented — username-only, 24 curated sites, evidence bundle, reconciliation; **no stuck-scan recovery**, no retry | Good — live pipeline + normaliser covered; worker death untested | **Partially implemented** |
| **Exposure reports / findings** | Partially implemented — detail page is strong (evidence, status, actions); list lost status/sort/category filters and the link to detail | Complete — persistence, risk, verification columns, reconciliation | Partial — detail/removal covered; list filters never tested | **Partially implemented** |
| **Credential monitoring** | UI only — empty state asserts "clear" while no provider exists; component computes `$provider`/`$demoMode` that the view never renders | UI only — live mode always returns 0 exposures; no provider integration | Unit-only — scoring impact tested; no breach path exists to test | **UI only** (and currently misleading — **S-03**) |
| **Removal guidance** | Partially implemented — centre + detail with status machine, notes, playbooks | Partially implemented — state machine, seeded playbooks, auto-created tasks; **no automated request engine**; reappearance alert claims a new task that is not created | Partial — status transitions and owner scoping covered | **Partially implemented** |
| **Continuous monitoring** | Partially implemented — preferences, cadence, next-run, alert list | Partially implemented — hourly dispatcher, cadence advance; **no plan check**, alerts only in demo mode or for `critical`, `next_scan_at IS NULL` never picked up | None — dispatcher untested | **Partially implemented / Needs verification** |
| **Subscriptions & billing** | UI only — plan badge, "Compare plans" dead link, demo self-grant button | Backend only — models + migration + seed rows; **no gateway, no invoice, no webhooks** | Only plan-gate assertions | **Not started** |
| **Reports** (generation + download) | Complete — list, generate, download | Complete — HTML dossier, signed route, token, audit, counter. `Report::downloadUrl()` is broken | Good — signed URL, tamper, owner scoping | **Complete** (HTML only; no PDF, decorative signature — **F-08**) |
| **Settings / account lifecycle** | Complete — profile, email change, purge, export, delete with OTP | Complete — all four flows with challenges | Complete — all four covered | **Complete** (minus consent withdrawal — **F-04**) |
| **In-app notifications** | **Broken/unused** — `NotificationsPanel` exists but is included in no layout | Backend only — 3 notifications write to the `database` channel; nothing reads them | None | **Backend only (write-only)** |
| **Email notifications** | n/a | Not started — every notification uses `['database']` only | None | **Not started** |
| **Real-time updates** | Partially — Livewire polling works | Backend only — 6 `ShouldBroadcast` events, channels authorised, no consumer, `BROADCAST_CONNECTION=log` | None | **Backend only** |
| **Audit log** | Minimal — last N rows on Settings; no admin view | Complete for a subset (auth, consent, scan start, removal, report download, export, deletion) | Partial | **Partially implemented** |
| **Data retention / purge** | Complete — purge button + confirmation | Partially implemented — scheduled command; **orphans findings** when a scan is pruned; no retention for credentials/alerts/consent; retains IPs after account deletion | Covered at feature level only | **Partially implemented** |
| **Admin / human review / back-office** | — | **Not started** | — | **Not started** |
| **Design system** | Partially implemented — 3 new / 9 mixed / 20 legacy; shim keeps them legible | n/a | None | **Partially implemented** |
| **Landing page** | Complete in the new world | n/a | Only "returns 200" | **Complete**, with claim fixes needed (**S-03**, **U-13**) |

---

## F. Last development milestone

### F.1 There is no Git history to read

No `.git` at the project root or in `traceless-app` [C]. No branches, no commits, no staged changes, no reflog. The reconstruction below is from **file modification times**, **file contents**, and the **compiled-view cache**, which preserves rendered snapshots of earlier template versions [C].

### F.2 The timeline, reconstructed

**Phase 1 — Product build (2026-11-05 → 2026-11-12)**

- `tl.md` written and the Laravel app scaffolded (Stock Laravel files dated 2025-01-24; project files from 11-05) [C].
- Schema, models, Livewire components, services, tests, seeders, docs and Docker built out. Stitch design exports (`stitch_ui/`, the zip) were produced on 11-05 [C].
- Videos copied into `public/assets/videos/` on 11-12 14:29–14:33 [C].

**Phase 2 — Verification hardening (2026-11-13 19:52 → 20:20)**

The most recent *engineering* work is a coherent, deliberate pass on scan trustworthiness:

| Time | File | Change |
|---|---|---|
| 19:52 | `app/Services/ScannerResultNormalizer.php` | Untrusted-payload allow-listing |
| 19:53 | `PythonScannerClient.php`, `Finding.php`, `ScanSource.php`, migration `add_scan_verification` | Process boundary + evidence columns |
| 19:55 | `ScanOrchestrator.php` | Verification persistence, reconciliation of absent findings |
| 19:58 | `config/traceless.php` | Retention, plans, monitoring cadences |
| 19:59 | `resources/views/livewire/finding-show.blade.php` | "How this was checked" evidence panel |
| 20:00 | `CredentialExposures.php` | Provider-aware mount |
| 20:02–20:03 | unit + feature tests | Normaliser and live-mode coverage |
| 20:05–20:11 | README, Dockerfile, compose, `.env.example`, `.env.staging.example`, `docs/DEPLOYMENT.md` | Staging/deployment runbook |
| 20:20 | `.env` | Local env finalised (Neon + Resend key present) |

This is the point where the product was **last functionally complete and coherent**. All 50 tests pass at this level [C].

**Phase 3 — UI/UX redesign, cut off mid-pass (2026-11-14 02:05 → 02:42)**

| Time | Artefact |
|---|---|
| 02:05 | `new uiux.md` (new owner brief) |
| 02:11 | Vite production build (`app-Bus51ifT.css`) |
| 02:13 | Nine core views rewritten into the obsidian world: landing, marketing layout, app layout, dashboard, scan-progress, identity-setup, findings-index, credential-exposures, removal-center |
| 02:28 | `PRODUCT.md` (Impeccable `init`) |
| 02:29 | `.impeccable/briefs/home.md` (surface brief for the pass) |
| 02:33 | `app.css` + `tailwind.config.js` — new tokens **plus a legacy compatibility shim** |
| 02:34 | auth shell + auth views + three shared components |
| 02:35 | `landing.blade.php` (second rewrite) |
| 02:36 | `routes/web.php` (**`/__preview/dashboard` local auto-login**) + dashboard + rebuild (`app-Blz8Feto.css`) |
| 02:42 | `layouts/app.blade.php`, `layouts/marketing.blade.php` — **final writes** |

### F.3 Exactly where it stopped

Development stopped **between "rebuild the shell and the primary screens" and "migrate the remaining screens"** [C].

The evidence that this is a mid-pass stop rather than a finished pass:

1. **The brief's own finish condition is unmet.** `.impeccable/briefs/home.md` states the build "ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance". None of those exist [C].
2. **A compatibility shim was written, then the pass stopped before removing the need for it.** `app.css:274–480` re-implements the old vocabulary (`.glass-card`, `.badge*`, `.btn-primary`, `.input-base`, …) in dark colours, and `tailwind.config.js:21–37` remaps legacy colour names "so untouched inner views stay legible". Twenty views still depend on that shim [C].
3. **The last two edited files are the two layouts** — the shell was being finalised when work ceased [C].
4. **The build is older than the last edits** (02:36 vs 02:42) [C].
5. **A design-review convenience route was added at 02:36 and never removed** (`/__preview/dashboard`, local-only auto-login as `test@example.com`) [C].

### F.4 Concurrent-work hazard observed during the audit

While this audit was running (02:24–02:42), files in the working tree were being **modified by another process**: `tailwind.config.js` changed at 02:33:39, `app.css` at 02:33:56, and the two layouts at 02:42 — after I had already read earlier versions [C].

Consequences, which the recovery plan must assume:

- Any audit or build taken before 02:42 is stale.
- Two AI agents edited the same tree with **no version control and no locking**, so a lost or clobbered edit cannot be detected or recovered.
- This audit is pinned to the **02:42 snapshot**; a later `diff` against Git (once created) is the only reliable way to know what changed next.

### F.5 Confirmed vs. assumed

**Confirmed by evidence:** the timeline, the file-level state, the failing/complete features, the applied migrations, the test results, the shim, the 20/9/3 view split, the light-surface defects, the `/__preview` route.

**Reasonable assumption (not provable from the tree):** that Phase 3 was performed by a *different* agent than Phase 2 (the Impeccable skill is installed for the Gemini CLI, not for this harness, and the visual vocabulary changes completely). The plan should not depend on this assumption — only on the observable fact that the redesign is unfinished.

---

## G. Outstanding issues

Severity: **Critical** (blocks launch or risks data/security) · **High** · **Medium** · **Low**.
IDs are stable and are reused in the roadmap and the master TODO.

### G.1 Critical bugs

| ID | Issue | Evidence |
|---|---|---|
| **C-01** | **No version control.** The entire product is unversioned on one disk; CI cannot run; no rollback; concurrent agents can overwrite each other silently. | no `.git` at root or app; `.github/workflows/ci.yml` present but unusable |
| **C-02** | **A dead scan blocks the account from scanning again.** `RunScanJob` has `$tries=1`, `$timeout=180`, **no `failed()` handler**; any hard kill/timeout leaves the scan non-terminal; `activeScan()` treats every non-terminal status as active, and both scan entry points redirect to it. | `traceless-app/app/Jobs/RunScanJob.php`, `app/Models/IdentityProfile.php:90-93`, `app/Livewire/Scans/ScanCreate.php:67-69`, `app/Livewire/Dashboard/Dashboard.php:126-128` |
| **C-03** | **Deep Scan is unobtainable in production.** Plan gating exists, but the only way to obtain the plan is `enableDemoDeepScan()`, which is 403 unless `TRACELESS_DEMO_MODE` is true. With demo off (the production requirement), no user can ever activate Deep Scan, recurring monitoring, or deep reports. | `app/Livewire/Scans/ScanCreate.php:98-112`, `app/Livewire/Monitoring/MonitoringPage.php:112-120`, `app/Models/User.php:79-90` |
| **C-04** | **Credential monitoring is unreachable and misrepresented.** Live scans never populate exposures, yet the credentials page asserts "All monitored identifiers are clear…" and the dashboard says "No active credential exposures found across public disclosure lists." | `app/Services/ScanOrchestrator.php:459-463`, `resources/views/livewire/credential-exposures.blade.php:69`, `resources/views/livewire/dashboard.blade.php:194-196` |
| **C-05** | **A local-only route logs in as the demo user.** `/__preview/dashboard` authenticates as `test@example.com` and redirects to the dashboard; guarded only by `app()->environment('local')`. Any deploy that ships a `local` env is a full authentication bypass. | `routes/web.php:98-107` |
| **C-06** | **Demo OTP display is an account-takeover path if it survives to production.** `otp.demo_display` defaults to the demo-mode value; when true, the 6-digit code is rendered on screen. Because a login code is issued for any existing address, whoever requests the code can read and use it. No boot-time guard prevents `demo_display=true` with `APP_ENV=production`. | `config/traceless.php:43`, `app/Http/Controllers/AuthController.php:96-115,140,292-306` |

### G.2 Security vulnerabilities & privacy risks

| ID | Issue | Evidence |
|---|---|---|
| **S-01** | See **C-06** (demo OTP display). Treat as the highest-priority security fix. | as above |
| **S-02** | See **C-05** (`/__preview` auto-login). | as above |
| **S-03** | **False security claims in shipped UI**: "STATUS: ENCRYPTED" (`layouts/app.blade.php:45`), "identifiers validated and encrypted" (`pages/security.blade.php:39`), "account changes … are rate limited" (`pages/security.blade.php:18` — there is no limiter on Settings OTPs), and "Checkout is delegated to a PCI-compliant provider" (`livewire/settings-page.blade.php:83`). No encryption at rest and no checkout exist. | as cited |
| **S-04** | **Authorization policies are dead code.** Five policies define view/update/delete rules; not one is invoked. Protection depends entirely on hand-written `abort_unless` in each component (currently correct, but one omission is a data breach). | `app/Policies/*.php`; zero `authorize(`/`Gate::`/`@can` matches in `app/` |
| **S-05** | **No encryption at rest** for identifiers, phone numbers, emails, notes or evidence, despite claims. | no encrypted casts; no `Crypt::` usage |
| **S-06** | **OTP plaintext reaches the log file** whenever `MAIL_MAILER=log` (the default local setting) — 8 rendered OTP mails were found in `storage/logs/laravel.log` during the backend review. | `app/Services/OtpService.php:46-50`, `config/mail.php`, local `.env` |
| **S-07** | **Account deletion leaves IP addresses behind.** `audit_logs.user_id` is `nullOnDelete`, so IP + action survive; consent records are cascade-deleted, destroying the compliance proof. | migration `2026_11_05_000001:166-173`, `app/Livewire/Settings/SettingsPage.php:225-233` |
| **S-08** | **Insecure defaults ship in the template.** `.env.example` sets `TRACELESS_OTP_DEMO_DISPLAY=true`; the local `.env` contains no `TRACELESS_*` keys at all, so demo mode **and** on-screen OTP are both on by default. | `.env.example:97-105`, local `.env` |
| **S-09** | **Live secrets sit in the working tree.** `.env` holds a live-looking Resend key, a Neon connection string for the `production` branch, and an Upstash token. `.gitignore` would exclude `.env` — but there is no repo yet, and the `.env` is shared with `docker-compose.yml`. | `.env` (names only; values deliberately not reproduced) |
| **S-10** | **No rate limiting on account-change OTPs** in Settings beyond the 60 s per-address cooldown; Livewire endpoints are not route-throttled. | `app/Livewire/Settings/SettingsPage.php:64-68,157-161` |
| **S-11** | **Registration enumerates accounts** via `unique:users,email` (login does not). | `app/Http/Controllers/AuthController.php:31` |
| **S-12** | **Policies/claims vs. consent withdrawal:** privacy page promises withdrawal by deleting the identity; no such feature exists and `revoke()` is unreachable. | `pages/privacy.blade.php:28`, `app/Services/ConsentService.php:47-51` |
| **S-13** | **No security headers** are emitted (no CSP, HSTS, frame-ancestors); `bootstrap/app.php` registers no middleware. | `bootstrap/app.php:14-19` |

### G.3 Incomplete functionality

| ID | Issue | Evidence |
|---|---|---|
| **F-01** | **Deep Scan is a relabelled snapshot.** `type='deep_scan'` changes nothing in the pipeline except whether demo monitoring alerts fire; there is no extra source coverage, no extra stage. | `app/Services/ScanOrchestrator.php:84-227`, `:527` |
| **F-02** | **No automated removal.** `workflow_type='automated'` is a label; every task is manual tracking. | `app/Services/RemovalWorkflowService.php:23-67`, `ScanOrchestrator.php:453` |
| **F-03** | **Reappearance flow is inconsistent.** A removed finding becomes `reappeared` and the alert says "A new removal task is ready", but no new task is created and the old task stays `removed`. | `app/Services/ScanOrchestrator.php:327-341,509-519` |
| **F-04** | **No identity delete, archive or consent withdrawal.** `IdentityProfile` uses `SoftDeletes` but nothing ever deletes; policies define `delete` with no feature. | `app/Models/IdentityProfile.php`, `app/Policies/IdentityProfilePolicy.php` |
| **F-05** | **Monitoring alerts fire only in demo mode or for `critical` findings.** Live monitoring can therefore complete with new findings and produce no alert. | `app/Services/ScanOrchestrator.php:198-200,521-529` |
| **F-06** | **Monitoring is not plan-gated in the dispatcher**, and subscriptions with `next_scan_at = NULL` are never scheduled. | `app/Jobs/DispatchDueMonitoringScans.php:26-54` |
| **F-07** | **E-mail notifications not implemented**; in-app notification rows are written but nothing reads them, and `NotificationsPanel` is included in no layout. | `app/Notifications/*.php`, `resources/views/livewire/notifications-panel.blade.php` |
| **F-08** | **Report signature is decorative.** It is computed and rendered but never verified; `Report::downloadUrl()` produces an unsigned URL that a `signed` route rejects. | `app/Services/ReportService.php:53-55`, `app/Models/Report.php:40-43`, `routes/web.php:83-85` |
| **F-09** | **No admin / human-review surface** for high-risk findings, despite the brief requiring human review. | no admin routes or components |
| **F-10** | **Retention is incomplete.** Pruning a scan nulls `findings.scan_id` for newer findings (orphans them); credentials, alerts, consent and removal tasks have no retention; the purge writes no audit entry. | `app/Console/Commands/PruneTraceLessData.php:42-51` |
| **F-11** | **Subscriptions/billing not started** (models and seed rows only). | `app/Models/Subscription.php`, `BillingRecord.php`; no gateway |
| **F-12** | **Python `normalizer/normalizer.py` and `local-ai/safety_engine.py` are dead code** — nothing in PHP invokes them; `config('traceless.python.ai')` is never read. | `python-services/*`; only PHP-side `ScannerResultNormalizer` is used |
| **F-13** | **`MustVerifyEmail` is implemented but no verification middleware/routes exist.** | `app/Models/User.php:14`, `routes/web.php` |

### G.4 Integration issues

| ID | Issue | Evidence |
|---|---|---|
| **I-01** | **Broadcasting is emitted but never consumed.** Six `ShouldBroadcast` events, authorised channels, no Echo/Pusher client in `package.json`, no `window.Echo` in any view, `BROADCAST_CONNECTION=log`. `config('traceless.broadcasting.enabled')` is never read. | `app/Events/*`, `routes/channels.php`, `resources/js/*`, `config/broadcasting.php:4` |
| **I-02** | **Live scan silently "succeeds" with zero findings** when the profile has no monitored username: no sources, no explanation, progress still runs to 100 %. | `app/Services/ScanOrchestrator.php:252-261` |
| **I-03** | **The worker's specific failure is hidden.** `PythonScannerClient` logs only a hash of stderr; the user always sees one generic message ("The scan integration did not return a valid result"), so "Scanner worker is not installed" is never actionable in the UI. | `app/Services/PythonScannerClient.php:59-67`, `ScanOrchestrator.php:220-225` |
| **I-04** | **`docker-compose.yml` reads the local `.env`**, which sets `APP_ENV=local`, `APP_DEBUG=true`: bringing the stack up with the shipped `.env` runs a production image in debug mode. | `docker-compose.yml:8-9`, local `.env` |
| **I-05** | **Breach provider is a string, not an integration.** `TRACELESS_BREACH_PROVIDER` is read once into a view-model property that the view never renders. | `config/traceless.php:59-61`, `app/Livewire/Credentials/CredentialExposures.php:25` |

### G.5 UI/UX problems

| ID | Issue | Evidence |
|---|---|---|
| **U-01** | **The redesign covers 3 of 34 views.** 20 views still use the superseded vocabulary (`btn-primary`, `glass-card`, `badge`, `input-base`, `text-ink`) and depend on a compatibility shim; 9 are mixed. | view classification (§E, and `app.css:274-480`, `tailwind.config.js:21-37`) |
| **U-02** | **~30 light surfaces remain inside the dark world**, producing near-white panels behind near-white text on 11 screens: finding-show, identity-index, monitoring, scan-create, scans-index, settings, reports, removal-show, notifications-panel, pages/security, `welcome` (dead). Examples: `bg-white/40` header, `bg-white/60` option cards, `hover:bg-white`, `bg-white/95` dropdown. | `scan-create.blade.php:8,34,44`; `finding-show.blade.php:13,15,58,85,141`; `identity-index.blade.php:12,14,48,65,74`; `monitoring-page.blade.php:72`; `settings-page.blade.php:155,166`; `notifications-panel.blade.php:2,9,23,50`; `reports-page.blade.php:65,98`; `removal-show.blade.php:160`; `scans-index.blade.php:25,27`; `pages/security.blade.php:21,51` |
| **U-03** | **Scan progress screen lost its controls and evidence.** The component passes `$findings` and `$sources` and exposes `cancel()`, but the view renders neither, has no cancel button, no error state and no retry. On failure it still displays "ENCLAVE: LOCAL OSINT WORKER ACTIVE". | `resources/views/livewire/scan-progress.blade.php:1-65` vs `app/Livewire/Scans/ScanProgress.php:23-43` |
| **U-04** | **Broken Livewire control:** `wire:click="removeUsername(…)`" has no matching method (the component only has `removeChip`). | `identity-setup.blade.php:56`, `app/Livewire/Identity/IdentitySetup.php:87` |
| **U-05** | **Identity setup dropped declared fields:** `previous_names`, `aliases`, `email_input`, `phone_input`, `url_input` are never rendered, so they can never be set from the UI even though export/report include them. | `identity-setup.blade.php` (0 references) vs `IdentitySetup.php:17-33` |
| **U-06** | **Findings list lost its filters and its route to detail:** `statusFilter`, `sort` and category filters are declared but not rendered; no row links to `findings.show`. | `findings-index.blade.php` vs `app/Livewire/Findings/FindingsIndex.php:22-33` |
| **U-07** | **Demo data is not labelled** on the dashboard, the findings list, or the credentials page — contradicting PRODUCT.md's "always labelled" rule. | `dashboard.blade.php` (0 demo refs), `findings-index.blade.php` (0), `credential-exposures.blade.php` (0) |
| **U-08** | **Dead link:** "Compare plans" → `/#pricing`; the landing page has no `id="pricing"`. | `settings-page.blade.php:78`, `landing.blade.php` anchors |
| **U-09** | **Invalid Tailwind class** `object-fit-cover` (should be `object-cover`) on the processing video. | `scan-progress.blade.php:3` |
| **U-10** | **Two parallel component vocabularies** (`ops-card`/`btn-ops-primary` vs `glass-card`/`btn-primary`) produce near-duplicate styles that will drift. | `app.css:87-211` vs `:274-480` |
| **U-11** | **Hardcoded decorative identifiers** ("TR-782", "PROTOCOL: RECON-V4", "NODE: LAT-LON ENCRYPTED") presented as system state. | `layouts/app.blade.php:45`, `landing.blade.php:47-48` |
| **U-12** | **Front-end build is stale relative to the last edits** (build 02:36; layouts 02:42). New utility classes introduced after the build may be absent from the compiled CSS. | `public/build/manifest.json` mtime vs layout mtimes |
| **U-13** | **Unsupported marketing claim** "500+ open platforms" in the overview copy, while live coverage is 24 curated sites. | `landing.blade.php` overview section |
| **U-14** | **Dead view** `welcome.blade.php` (176 lines, inlines a full Tailwind build) is routed nowhere. | `routes/web.php:27` renders `landing` |

### G.6 Performance problems

| ID | Issue | Evidence |
|---|---|---|
| **P-01** | **Artificial per-stage sleeps** (`TRACELESS_SCAN_DELAY_MS`, default 900 ms) run inside the queue worker, extending every scan by several seconds for visual effect. | `app/Services/ScanOrchestrator.php:501-507`, `config/traceless.php:48` |
| **P-02** | **Progress polling every 1.5 s** per open scan page, hitting the database each time; no backoff after completion. | `scan-progress.blade.php:1` |
| **P-03** | **N+1 / memory**: dashboard and report generation load all findings into memory for grouping and risk counting; fine at demo scale, not at years of scans. | `app/Livewire/Dashboard/Dashboard.php:75-103`, `ReportService.php:14` |
| **P-04** | **One notification per critical finding** with no batching → notification storm on a large live scan. | `ScanOrchestrator.php:370-379,546-562` |
| **P-05** | **Two large video files** (7.4 MiB and 7.5 MiB, ≈15 MiB total) served from the origin with no poster image, no `preload` strategy for the second video, and no CDN guidance. | `public/assets/videos/*` sizes |
| **P-06** | **Missing indexes for retention queries** (`scans(status, created_at)`, `reports(created_at)`, `audit_logs(created_at)`, `alerts(is_read)`). | migrations vs `PruneTraceLessData.php:24-29` |

### G.7 Deployment problems

| ID | Issue | Evidence |
|---|---|---|
| **D-01** | **No repository, so no CI, no rollback, no review.** Vendored `.git` directories under `python-services/upstream/` will corrupt a naive `git add .`. | no `.git`; `python-services/upstream/*/.git` |
| **D-02** | **Production flags are not enforced in code.** Nothing fails the boot when `APP_ENV=production` and `TRACELESS_DEMO_MODE=true` or `TRACELESS_OTP_DEMO_DISPLAY=true` or `APP_DEBUG=true`. | `bootstrap/app.php`, `config/traceless.php` |
| **D-03** | **`.env` currently points local development at the Neon `production` branch** with `APP_DEBUG=true`. | local `.env`, `.neon` |
| **D-04** | **Compose uses the local `.env`** (see **I-04**). | `docker-compose.yml:8-9` |
| **D-05** | **No domain, TLS, `APP_URL` or mail-domain verification** is set up; `APP_URL=http://localhost` locally and a placeholder in the staging template. Signed report URLs therefore point at the wrong host. | `.env`, `.env.staging.example:10` |
| **D-06** | **No monitoring, alerting, log aggregation, uptime check or error tracking** is configured. The only health signal is `/up`. | `bootstrap/app.php:12`, `Dockerfile:74-75`; no Sentry/Flare/OTel |
| **D-07** | **Backups are unverified.** DEPLOYMENT.md relies on Neon PITR without a recorded retention window or a restore drill. | `docs/DEPLOYMENT.md:193-202` |
| **D-08** | **Queue and scheduler are separate services**; a deploy that only runs `web` leaves every scan `queued` forever with no UI detection. | `docker-compose.yml`, `docker/supervisord.conf` |
| **D-09** | **CI never exercises the Python tests** and cannot run at all without a repository. | `.github/workflows/ci.yml` |
| **D-10** | **Container runs as root for supervisor/nginx**; only PHP-FPM workers drop to `www-data`. Acceptable, but must be a documented decision. | `Dockerfile:41-78` |
| **D-11** | **No legal/operational prerequisites**: no company entity details in the footer, no DPA, no cookie/consent banner (no cookies are set beyond the session), no support contact. | `layouts/marketing.blade.php` footer |

---

## H. Launch readiness

### H.1 Readiness by dimension

| Dimension | State | Verdict |
|---|---|---|
| Working software | Core flows work; 50 + 14 tests pass; migrations in sync | **Amber** |
| Source control & release engineering | No repository, no CI execution, stale build | **Red** |
| Authentication | Strong design; one configuration-time takeover path | **Amber** |
| Authorization | Correct inline checks; unused policy layer; no admin surface | **Amber** |
| Privacy engineering | Consent gate is real; retention incomplete; no encryption; false "encrypted" claims | **Red** |
| Core product promise (scan → findings → removal) | Scan and findings work; removal is tracking only; removal automation absent | **Amber** |
| Credential monitoring | Not integrated and currently misrepresented | **Red** |
| Monetisation | Not started; Deep Scan unreachable in production | **Red** |
| UI/UX | Strong marketing + shell; 20 screens unfinished; light surfaces on dark | **Amber** |
| Accessibility | Reduced-motion + focus styles present; no audit, no automated checks | **Amber** |
| Performance | Fine at demo scale; artificial delays; no CDN | **Amber** |
| Infrastructure | Docker + compose + runbook are good; no domain/SSL/monitoring/backups verified | **Red** |
| Legal/compliance | Policy pages exist and are unusually honest; no entity, DPA, or provider disclosures; false encryption claims | **Red** |

### H.2 Go / no-go checklist

| Requirement for launch | Met? |
|---|---|
| Reproducible build from a clean checkout | **No** (no repo) |
| Automated tests green in CI | **No** (CI has never run) |
| Demo mode provably disabled in production | **No** (no enforcement) |
| OTP never displayed in production | **No** (no enforcement; default on) |
| No environment-gated auth bypass | **No** (`/__preview/dashboard`) |
| Paid capability purchasable, or product positioned as free | **No** |
| Credential feature either integrated or clearly marked unavailable | **No** |
| Every user-facing security claim true | **No** ("encrypted", "real-time", "PCI-compliant") |
| Data export and deletion work | **Yes** |
| Retention command scheduled | **Yes** (with defects) |
| HTTPS, domain, secure cookies | **No** |
| Monitoring/alerting/error tracking | **No** |
| Backups verified by restore | **No** |
| Legal pages, entity details, contact | **Partial** |

**Overall: NO-GO.** Estimated remaining effort to a defensible public launch, assuming one senior full-stack engineer plus design support: **6–9 weeks**, dominated by monetisation, the design finish, and infrastructure — not by core engineering.

### H.3 What is genuinely close to done

- Authentication and account lifecycle.
- Consent-gated scan orchestration with evidence.
- Findings, removal tracking, reports, export, deletion.
- The marketing surface and the authenticated shell.
- The deployment topology and runbook.

These should be preserved, not rebuilt. The risk in a recovery project of this shape is **rewriting working subsystems**; the roadmap is deliberately biased toward finishing and hardening.

---

## I. Recommended next steps

Ordered by leverage. Each maps to a task ID in `TRACELESS_LAUNCH_ROADMAP.md` and `TRACELESS_MASTER_TODO.md`.

1. **Create the Git baseline today** (`P0-01`). `git init` at the project root, exclude vendored upstream `.git` directories via `.gitignore`/submodule decision, commit the 02:42 snapshot as `baseline-2026-11-14`. Nothing else should start before this exists.
2. **Close the three production hazards** (`P0-02`, `P0-03`): delete `/__preview/dashboard`; add a boot-time guard that throws when `APP_ENV=production` and demo mode / OTP display / debug are on; separate the local `.env` from the Neon `production` branch.
3. **Make the failure modes honest** (`P1-01`–`P1-04`): add `failed()` + stuck-scan recovery to `RunScanJob`; label demo findings everywhere; replace the credentials "all clear" state with "provider not configured"; fix `Report::downloadUrl()` and the `#pricing` link.
4. **Finish the design pass that was cut off** (`P4-01`–`P4-05`) — but only after steps 1–3, and using the existing Impeccable artefacts (`PRODUCT.md`, the surface brief) rather than restarting from zero.
5. **Decide the monetisation model before building billing** (`P3-01`): free launch with Deep Scan as "coming soon", manual invoicing, or a real gateway. This decision changes the UI, the gating code and the legal pages.
6. **Decide the credential-monitoring story** (`P2-04`): integrate a breach provider, or remove the feature from navigation and marketing until it exists.
7. **Harden privacy engineering** (`P3-02`–`P3-05`): encryption at rest for identifiers/notes, log redaction for OTP mail, retention for credentials/alerts/consent, deletion that also pseudonymises audit IPs, and consent withdrawal.
8. **Build the production environment** (`P6-01`–`P6-05`): real host, domain, TLS, Postgres/Redis separation from the production branch, monitoring, backups with a restore drill.
9. **Expand the test suite where it is blind** (`P5-01`): monitoring dispatcher, retention command, events/channels, report URL signing, component-level Livewire tests, and the four scan failure modes.
10. **Then run a full launch rehearsal** (`P7-01`): the README verification checklist end-to-end on a production-like environment, with demo mode off and a real mailbox.

**Recommended immediate next task:** `P0-01` — put the frozen 02:42 snapshot under Git and tag it. It takes minutes, is reversible, requires no product decision, and every other item on this list depends on being able to see and undo changes.

---

## J. Investigation method and limitations

### What was inspected

- **All** PHP under `app/` (71 files, 5,104 lines), all 9 migrations, all 11 test files, all 3 route files, all 12 config files, `bootstrap/app.php`.
- **All 34 Blade views** (3,397 lines) and `app.css` (523 lines), `tailwind.config.js`, `vite.config.js`.
- `Dockerfile`, `docker-compose.yml`, `.github/workflows/ci.yml`, `.env.example`, `.env.staging.example`, `.env` (key names and danger flags only), `.neon`, `.neon`/`neon.ts`, `.gitignore`.
- The Python worker's contract and safety rules, the test suite, `requirements.txt`, and the vendored upstream projects' licences and presence.
- **All** project documentation: README, DEPLOYMENT, PRODUCT.md, the Impeccable surface brief, `.stitch/DESIGN.md`, `new uiux.md`, `tl.md`.
- Binary assets: video duplicate verification by SHA-256; Stitch export inventory; zip manifest.

### Commands executed (read-only)

| Command | Result |
|---|---|
| `php artisan test` (with storage redirected to a temp dir) | **50 passed, 253 assertions, 18.22 s** |
| `python -m unittest discover -s python-services/scanner/tests` | **14 passed, 4.05 s** |
| `php artisan migrate:status` | all 9 migrations **Ran** (batches 1–2) |
| `php artisan serve` + HTTP probes | `/`, `/login`, `/register`, `/privacy`, `/terms`, `/security`, `/up` → **200**; `/dashboard` → **302** to login; Vite CSS/JS and both videos → **200** |
| `php -v`, `php -m`, `node -v`, `npm -v`, `python --version` | PHP 8.2.12 (pdo_pgsql, pdo_sqlite, mbstring, openssl, gd, zip present); Node 24.11.1; npm 11.6.2; Python 3.14.3 |
| File-hash manifest of 191 project files | snapshot pinning (temp, not in repo) |

### Limitations, stated plainly

1. **No visual screenshots.** Chromium/Edge cannot start under this session's Windows sandbox (`Mojo platform_channel` / `CreateProcessAsUser` access denied) [C]. The UI assessment is therefore based on rendered HTML fetched over HTTP, the Blade source, the compiled CSS, and the class/token analysis — **not** on pixels. The light-surface findings are derived from CSS composition and are high-confidence but were not visually confirmed.
2. **First test run failed 13 tests for environmental reasons.** The session's restricted token could not write `storage/logs/laravel.log`, `storage/framework/views`, or `.phpunit.result.cache`. Re-running with `VIEW_COMPILED_PATH` and `LOG_CHANNEL=stderr` pointed at the harness temp directory produced **50/50 green** [C]. Any future CI must either run as a user that can write `storage/` or apply the same redirection.
3. **The shell sandbox broke twice** during the audit (`windows-acl-run: CreateProcessAsUserW failed`, Win32 2), which is why two delegated sub-audits produced no result and part of the investigation was completed directly. No application data was affected.
4. **Live external integrations were not exercised**: no real OTP e-mail was sent, no live scan was run against the public internet from this session, no payment or breach provider exists to test.
5. **The database was only inspected read-only** (`migrate:status`). Row counts and data quality were not examined; no production data was read, written or altered.
6. **Docker images were not built.** The Dockerfile/compose assessment is static.
7. **A stray probe file remains**: `traceless-app/storage/framework/views/.dsh-write-probe` was created by the file tool to test write capability and could not be deleted from the restricted shell. It is inside the compiled-view cache and is harmless; delete it when convenient.

### Documentation cross-reference note

Where the docs and code disagreed, §D.2 records the discrepancy rather than silently preferring one. Where a document describes a **future** state (the Impeccable brief, `new uiux.md`), it is treated as intent, not evidence of implementation.
