# TraceLess — progress snapshot

_Last updated: 2026-11-23 (end of day)_

## Where it is

- **Live beta:** https://traceless.orrddy.store (HTTPS, valid certificate, Cloudflare edge).
- **Production host:** Altare `tracelessvps` app (Always Free plan) running one Docker
  container: nginx + PHP-FPM + `queue:work` + `schedule:work` under supervisor.
- **Image:** `ghcr.io/orrddy/traceless:latest` (also tagged with each commit SHA), built by
  GitHub Actions on every push to `main`; migrations run automatically in the entrypoint.
- **Database:** Neon Postgres (direct endpoint, not the pooler).
- **Mail:** Resend with verified domain `orrddy.store`; sender
  `TracelessBot <do-not-reply@orrddy.store>`. A live send returned HTTP 200.
  The old DirectAdmin SMTP relay is kept as a documented fallback.
- **Old host:** weeny.cloud `weeny-traceless` service is **stopped**; Altare is the only
  production instance.

## What was completed today (2026-11-23)

### Deployment & CI
- Moved the CI workflow to the repository root (GitHub ignores nested workflows) and made it
  green: tests Job + `docker/build-push-action` to GHCR on every `main` push.
- Fixed the chain of CI-only failures: Pint drift, composer booting the production safety
  guard with no `.env`, `APP_ENV` leaking into PHPUnit (CSRF 419s), uppercase GHCR name, and
  the Dockerfile path for buildx.
- Production moved from weeny to Altare; domain CAA fixed (ssl.com) and custom domain issued.
- Stopped weeny; verified Altare stays up.

### Brand & interface
- Vercel-style design language applied app-wide (not just the dashboard): light `#FAFAFA`
  default, working dark toggle that restores the original operations palette.
- Dashboard rebuilt: metric row, findings table, scan activity, credential exposures,
  removal pipeline, risk distribution, alert strip, empty states, mobile layouts.
- Landing page rebuilt: hero with typed headline and cursor, italic-loop subtitle, product
  film with interactive water-ripple shader, marquee guarantee strip, horizontal "what we
  check" slider (including a v2.0 image/face deep-scan card), rotating Nevera wordmark,
  free-beta pricing + v2.0 roadmap, live IP intelligence card with 48h countdown.
- Two small DOM crawlers walk the word strip, tear letters apart and move on; hover steers
  them, click feeds a word; all disabled under reduced motion.
- Effects are original implementations (no third-party code copied); the unlicensed
  inspiration repos are credited in the JS header comment.

### IP intelligence
- New engine: `app/Services/IpIntelligence/*`, four providers (ipapi.is, proxycheck.io,
  AbuseIPDB, IP2Location.io) queried concurrently, weighted scoring (0–100 + A–F grade),
  honest nulls/provider status, 48h persistent cache (shorter on partial coverage).
- Single-flight per IP (DB lock + token); 20 visitors = one analysis.
- Public `GET /api/ip-intelligence`: `ready` / `warming` / `422` invalid IP / `503` no
  providers configured. Stale zero-coverage cache rows self-heal once keys exist.
- Operator tools: `ip:forget {ip?} {--all} {--dry-run}`, daily `ip:prune` schedule.
- IP tool page rebuilt around the engine: big risk card, source coverage, geo, network,
  anonymity flags, abuse reputation, reasons, cache countdown.

### Beta & operations
- `BetaAccess` gate: while `TRACELESS_BETA=true`, every active account gets deep scans and
  recurring monitoring; v2.0 flips it off to restore plan checks. No billing path exists.
- Deep-scan wall-clock budget raised to 7200s (two hours); the queue worker timeout is 7800s and the database retry window 8100s so a long crawl is never re-delivered.
- Notifications queue (`ShouldQueue`); mail failures are recorded to `/admin/email` and
  surface as form errors instead of 500s.
- Retention: `traceless:prune` also prunes `outbound_emails`; IP cache prune daily.
- Admin: overview, users, activity log, scan inspector, failed jobs, email delivery,
  heartbeats, `ops:alert` watchdog.

## Verification status

- **212 PHP tests / 923 assertions** pass; Pint clean on 179 files.
- **14 Python tests** pass (scanner).
- Live checks today: `/up` 200, login OTP flow issues and redirects to `https`, CSS and
  Livewire JS load, admin pages reachable with a session, landing IP card mock-verified in
  light/dark/mobile (real provider result depends on production env keys).

## Blocking the 10 December beta

1. **Altare app environment** — paste `traceless-app/deploy/altare.env` into the app's
   Variables (Resend key, `do-not-reply` sender, IP provider keys, `TRACELESS_BETA=true`,
   `DB_QUEUE_RETRY_AFTER=720`, `MAIL_TIMEOUT=10`) and redeploy the latest image.
2. **Gmail/Outlook deliverability** — sign in with both and confirm inbox vs spam.
3. **Live deep-scan QA** — run one deep scan under the beta gate; check timing, findings,
   queue heartbeat, admin scan inspector.
4. **Uptime monitor** — external check on `/up` alerting email/phone.
5. **Backups** — Neon restore drill; nightly copy of the `/data` storage volume.
6. **Legal + support** — business name, country, support address, subprocessor list.
7. **Secrets** — rotate Altare SFTP password, API tokens, provider keys; decide repo
   visibility (currently public).

## Tomorrow's plan

1. Verify env + redeploy; Gmail/Outlook OTP proof; live IP card with real providers.
2. Deep scan end-to-end + admin sweep (failed jobs, email log, heartbeats).
3. QA pass every page in both themes at 1440/768/390.
4. Wire the external uptime monitor and a backup job.
5. Legal page copy + support inbox; rotate exposed secrets.
6. Then: beta waitlist page, image/face scan research for v2.0.

## Operations cheatsheet

```bash
# image build (automatic on push to main)
git push origin main   # GitHub Actions -> ghcr.io/orrddy/traceless:latest

# in the Altare console: App -> Image -> latest -> Redeploy

# container entrypoint runs: migrations, config/view cache, storage link, then supervisor
# web + queue + scheduler

php artisan ops:alert                 # watchdog (queue heartbeat, failed jobs, mail failures)
php artisan ip:prune                  # drop expired IP-intelligence cache rows (daily 03:40)
php artisan ip:forget <ip> --dry-run  # operator cache reset for one address
php artisan ip:forget --all --dry-run # entire IP cache, then --all to apply
php artisan scans:reap-stale          # every 10 minutes in the scheduler
php artisan queue:work --queue=scans,default
```

Admin (role-gated): `/admin`, `/admin/users`, `/admin/activity`, `/admin/scans/{id}`,
`/admin/failed-jobs`, `/admin/email`.

## Layout of new work

```
traceless-app/
  app/Services/IpIntelligence/        four providers, runner, scorer, repo, metrics, DTOs
  app/Http/Controllers/IpIntelligenceController.php   GET /api/ip-intelligence
  app/Jobs/AnalyzeIpIntelligence.php  single-flight background analysis
  app/Support/BetaAccess.php          free-beta gate (TRACELESS_BETA)
  app/Console/Commands/ForgetIpIntelligence.php
  app/Console/Commands/PruneIpIntelligence.php
  resources/js/landing-fx.js          ripple shader, crawlers, typewriter (original)
  resources/views/landing.blade.php   marketing page
  resources/views/livewire/tools/ip-check.blade.php   enriched tool
  tests/Feature/IpIntelligence*.php, BetaAccessTest, ScanBudgetTest
docs: README.md (status/log), this file, BRAND.md (design reference)
```

## Known limitations / honesty notes

- The beta bypasses plan checks by design; `TRACELESS_BETA=true` must be set in Altare for
  deep scans/monitoring to work for planless accounts.
- The landing IP card and tool depend on the four provider keys; without them the endpoint
  returns 503 by design rather than an all-unavailable cache record.
- Removal is guided/manual; no automated removal engine. The landing page no longer promises
  automated removals.
- Image/face deep scans are explicitly marked planned for v2.0, not shipped.
- The repo is currently public; treat any secret found in chat/history as exposed and rotate.

### Licensed third-party effects (added end of 2026-11-23)

- **Mouse Effects** (github.com/cortiz2894/mouse-effects) — commercial licence purchased;
  water-ripple shaders and three-target fluid simulation ported to vanilla WebGL2
  (`resources/js/vendor/water-ripple.js`) and applied as a full-bleed product-film background.
- **Web Crawlers** (github.com/cyohei9907/web-crawlers) — commercial licence purchased;
  the page-overlay spider adapted into a factory so two small crawlers run on the landing page
  (`resources/js/vendor/web-crawler-spider.js`). They walk real page words and glitch them.
- The film is now edge-to-edge (100vw) and upright; the previous custom ripple and custom
  crawler implementations were removed.
- Landing IP experience gained a client-side "👀 We see you" card: device, browser, device
  spec, screen/locale and battery (only when the browser exposes it). Nothing is sent to
  the server; no fingerprint is stored.
- IP cache retains the "Cached for 48h · fresh scan in …" countdown on both the landing card
  and the network tool page.
### Late 2026-11-23 — browser fingerprint panel

- Landing "👀 We see you" panel expanded to a full client-side readout: browser /
  engine / vendor, OS, device type/model, battery (level, charging, charge time),
  CPU, device memory, GPU vendor+renderer+capabilities, display, network estimates,
  UA/language/timezone, storage (LocalStorage/IndexedDB/quota), media capabilities,
  canvas+device fingerprint and local device id.
- Built on the MIT `@marufme/device-key` package plus direct Web APIs; nothing sent
  to the server, nothing persisted. Safari/iOS/Firefox gaps are labelled "Not
  exposed" with a per-browser privacy explanation; iPadOS is detected from the
  desktop-class Safari UA and every iOS browser reports the WebKit engine.
- Container image build fixed: `.dockerignore` no longer excludes
  `resources/js/vendor`, and `composer install` retries three times because GitHub
  dist endpoints intermittently answer 504 to CI traffic.
### 2026-11-24 — scan console, deep-scan email, uptime

- **Scan console**: `/scans/{id}` now loads instantly with a Three.js rotating Earth
  (NASA Blue Marble, public domain), atmosphere, orbital rings, moving scan band and
  pulsing hit points, plus a sci-fi HUD. Countdown is an **absolute server timestamp**
  derived from the queue time and the 2-hour deep-scan window, so signing out, signing
  in on another browser or opening a second phone shows the same remaining time.
  `/scans/{id}/status` exposes the same state as JSON; the page polls it and reloads
  when the scan reaches a terminal state.
- **Deep scan started email**: queued to the account email when a deep scan is created.
  It states the scan can take up to 2 hours, links to the live progress page and notes
  that progress continues while signed out. Uses the Nevera wordmark header.
- **Uptime monitor**: `.github/workflows/uptime.yml` probes `/up` from GitHub every five
  minutes and emails via Resend on failure. Requires repo secrets `RESEND_API_KEY` and
  `ALERT_EMAIL`; optional variable `PROD_URL`.
- Deep scans are now allowed to crawl for the full two hours (worker timeout 7800s, job timeout 7600s, queue retry window 8100s, stale reaper untouched because the scan row is refreshed between identifiers). They complete as soon as the crawl finishes; the
  2-hour figure is an upper-bound estimate, and the UI jumps to the results when done.
### 2026-11-24 — live dashboard, full-window deep scans, mobile navigation

- Dashboard now shows a **clickable live scan card** with a server-timed countdown,
  blinking status dot and a four-light (green/purple/red/brown) activity strip.
  The scan pill in the header also links to the console and counts down.
- A deep crawl that finishes early no longer collapses the progress screen: the
  UI keeps the full two-hour countdown with a calm “high traffic — sit back and
  relax, we will email you” state and hides the results. Both the result reveal
  and the completion notification happen at the same absolute server time.
- The queue now runs **two workers**; `/scans/{id}/status` exposes the queue
  position and whether the UI should still present the scan as active, so eleven
  simultaneous scans run two at a time with the rest showing queued.
- Mobile navigation is now grouped (Workspace/Tools/Account/Admin) with icons and
  an internal scroll area; the avatar in the top bar links to account settings.
- Browser titles are professional page names (Dashboard, Exposure report, Scan
  progress, Account settings, …) instead of ids and `//` separators.
### 2026-11-24 — realistic globe, scan-page spider, dashboard clarity

- Earth upgraded from a flat texture to a physically-lit blue marble with land
  relief, ocean specular, a separately rotating cloud shell and atmosphere glow
  (NASA Blue Marble, public domain). The graticule overlay was removed.
- One licensed Web Crawler now walks the scan console only; the landing page
  keeps its two raiders.
- Dashboard live card no longer overrides the theme background (it was a white
  box in dark mode), its “click to watch” copy and button were removed, and the
  card remains clickable with a server-timed countdown.
- “Run a scan” now opens Digital footprint so the identity and scan type are
  explicit; beta accounts default to the two-hour deep scan on the scan form.
### 2026-11-24 — random scan references, branded completion mail, page loader

- Scans now carry a **random public reference** (`TR-XXXXXXXXXX`) generated on
  create and used for URLs (`/scans/{public_id}`) and all user-facing labels;
  old numeric links keep working as a fallback. Database ids stay internal.
- The "Your TraceLess scan is complete" email now uses a fully branded template
  with the app icon, Nevera wordmark, reference, findings count, privacy score
  and a results button — Laravel's default button/URL footer is gone.
- Added a global branded **page loader** for the app and auth pages: an original
  monochrome dot-globe with three 3D orbit rings, a pulse core and Nevera
  wordmark. Reduced-motion, no-JS and timeout fallbacks are included; a custom
  export can still override it at `public/brand/page-loader.gif` (or
  .svg/.webp/.png).
### 2026-11-24 — loader on the landing page, white brand, orbit delay

- The monochrome dot-globe loader now also covers the marketing/landing layout.
- Loader backdrop is a deep navy gradient in both themes; the TraceLess Nevera
  wordmark, progress sweep and globe are white so the brand reads clearly.
- The loader keeps a deliberate ~1.1s minimum on screen (250ms with reduced
  motion) before fading, so the 3D orbit is actually visible on fast loads;
  movement uses linear GPU transforms with will-change for smoothness.
- Removed the landing-page crawler instruction strip; the licensed crawlers
  still run, they just no longer carry explanatory copy.
### 2026-11-25 — photoreal Earth and IP tool polish

- Earth on the scan console now uses the real NASA day/cloud/normal/specular maps
  (three.js planet textures, NASA public domain): natural land and cloud detail,
  ocean specular, a thin atmosphere rim, frontal sun lighting and a visible
  23.4° axis rotation. Camera pulled back and FOV narrowed so the whole globe is
  in frame; the sci-fi orbit rings and hit markers were removed for a Google-Earth
  look, leaving only a faint scan band.
- Fixed the global loader trapping mobile scrolling after the IP tool’s “Run
  check”: Livewire/XHR form submissions no longer trigger the navigation loader,
  and any interaction-shown loader auto-hides after 6s instead of leaving
  `overflow:hidden` on the page.
- IP check form controls now use a stable desktop grid with fixed-width buttons,
  and stack cleanly on mobile.
### 2026-11-25 — admin user intelligence, account states, report links

- Admin users table now shows **scan totals**, last sign-in **IP + device**, and
  monitoring state with pause/resume. Expanding a user shows scan activity
  (latest reference/status), recent sign-in locations plus active sessions,
  monitoring cadence/provider and account controls.
- New `user_login_events` table records IP, user agent and a readable device
  label at every successful OTP sign-in. Current devices are also read from the
  sessions table. (History starts from this deploy; earlier sign-ins were not
  retained.)
- Account states: **disabled** (suspended_at) and **pending verification**
  (email_verified_at null) with verify/enable actions. Pending accounts are
  signed out and blocked on their next request. The admin overview now shows
  pending/disabled counts, active monitoring and recent sign-ins.
- Removed the IP tool helper sentence and the discovery attribution footer.
- Report downloads: production URLs are rooted at the canonical HTTPS APP_URL
  and report links now live 24 hours instead of 30 minutes, fixing the proxy /
  expiry “Invalid signature” 403.