# TraceLess — Launch Roadmap

**From:** the frozen 2026-11-14 02:42 snapshot (see `TRACELESS_PROJECT_AUDIT.md`)
**To:** a stable, secure, production-ready public launch
**Planning assumption:** one senior full-stack engineer working the plan, plus design support during Phase 5; no parallel feature streams until Phase 0 is done.

---

## How this roadmap differs from the generic template

The brief proposed seven phases starting with "Project Recovery". The investigation changed the shape of that plan in four ways:

| Original assumption | What the audit found | Roadmap change |
|---|---|---|
| Recovery means "resolve blockers in broken code" | The code is not broken — 50 PHP + 14 Python tests pass. The blocker is **no version control**, plus three production hazards | **New Phase 0 — Baseline & Recovery** is about safety net and truth, not repair |
| "Complete essential user journeys" | Auth, consent, scanning, findings, removal tracking, reports, settings are **already complete**. The gaps are Deep Scan differentiation, credential monitoring, monitoring alerts and identity lifecycle | Phase 2 is narrower and targets specific seams |
| Monetisation is "if present" | There is **no payment integration at all**, and Deep Scan is unreachable in production | **Monetisation is promoted to its own phase with a decision gate** — it is a product decision before it is code |
| UI/UX transformation starts from the current design | A redesign **already started and is 3/34 screens complete**, with Impeccable artefacts on disk | Phase 5 **finishes** an in-flight pass; it does not commission a new one |

Two further changes: security/privacy is moved **after** the core product seams (it touches the same files, so doing it earlier causes rework), and testing is **continuous** rather than a terminal phase.

### What must not be rebuilt

The roadmap deliberately preserves: the OTP authentication design, the consent gate, `ScanOrchestrator`'s stage model, `ScannerResultNormalizer`, the evidence bundle, the removal state machine, signed report delivery, the deployment topology (Docker + compose + runbook), and the new obsidian/cyan visual world. These are the strongest assets in the codebase.

### Complexity scale

| Size | Meaning | Indicative effort |
|---|---|---|
| **S** | One file or a tight change, no design decision | ≤ 1 day |
| **M** | Multi-file change, tests, one decision | 2–4 days |
| **L** | Cross-cutting feature, migration or integration | 1–2 weeks |
| **XL** | External integration + product surface + ops | 2–4 weeks |

---

## PHASE 0 — Baseline & Recovery

**Goal:** make the project safe to change, and remove the hazards that make every later step risky.
**Exit criteria:** a tagged Git baseline; no environment-gated auth bypass; production-mode values that fail closed; a rebuilt front-end; a repeatable way to run the suite.

| ID | Task | Priority | Size | Depends on | Files / modules | Acceptance criteria | Definition of done |
|---|---|---|---|---|---|---|---|
| **P0-01** | Create the Git baseline and repository hygiene | **Critical** | S | — | project root, `.gitignore`, `python-services/upstream/` | `git init` at `C:\My Web Sites\DIGITAL FOOTPRINT`; vendored `python-services/upstream/*/.git` excluded (ignore or submodule decision recorded); `.env`, `.neon`, `database/*.sqlite`, `storage/`, `public/build`, `vendor/`, `node_modules/` ignored; first commit tagged `baseline-2026-11-14` | `git status` clean; `git log --oneline` shows the baseline; a second clone contains no secrets and no vendor dirs; `.env.example` alone is enough to boot |
| **P0-02** | Delete the preview auto-login route and add a production guard | **Critical** | S | P0-01 | `routes/web.php:98-107`, `bootstrap/app.php`, new `AppServiceProvider` guard | `/__preview/dashboard` removed; app **throws on boot** when `APP_ENV=production` and (`APP_DEBUG=true` or `TRACELESS_DEMO_MODE=true` or `TRACELESS_OTP_DEMO_DISPLAY=true`) | Route absent from `route:list`; a test asserts the boot guard throws for each unsafe combination; guard documented in DEPLOYMENT.md |
| **P0-03** | Separate local development from the production database branch | **Critical** | S | P0-01 | `.env`, `.neon`, `README.md` | Local dev runs on (a) SQLite or (b) a dedicated Neon dev branch — not the `production` branch; `APP_DEBUG` documented per environment | `migrate:status` on dev does not touch the production branch; README documents branch strategy; no production credentials in the working `.env` |
| **P0-04** | Rebuild front-end assets and verify the frozen snapshot renders | **High** | S | P0-01 | `resources/css/app.css`, `resources/views/**`, `public/build` | `npm ci && npm run build` succeeds; new manifest hash; every route returns 200/302 as expected | Build committed-locally (git-ignored in prod), fresh manifest, and a written record of the routes checked |
| **P0-05** | Make the test suite runnable in restricted environments | **High** | S | P0-01 | `phpunit.xml`, `README.md` | Documented command that keeps `VIEW_COMPILED_PATH` + `LOG_CHANNEL=stderr` writable, or a `storage/` permission note; suite green from a clean clone | CI and README use the same documented invocation; 50/50 PHP + 14/14 Python pass |
| **P0-06** | Record the audit, decision log and this roadmap in the repository | **Medium** | S | P0-01 | `TRACELESS_PROJECT_AUDIT.md`, `TRACELESS_LAUNCH_ROADMAP.md`, `TRACELESS_MASTER_TODO.md`, new `docs/DECISIONS.md` | Three documents committed; decisions log seeded with the open questions below | Documents linked from README; decisions have owners and dates |

**Open decisions to record during Phase 0**
1. Is the launch free-only, or paid? (drives Phase 3)
2. Is credential monitoring in the launch scope? (drives P2-04)
3. Which host, domain and email provider for launch? (drives Phase 7)
4. Single deployment of app + python-services, or separate scanner host? (drives P7-04)

---

## PHASE 1 — Truth & Trust Pass

**Goal:** make the product honest and unblock the user, before adding anything.
**Exit criteria:** no dead-end scan state; demo/live is unambiguous everywhere; no unverifiable security claim; every visible control works.

| ID | Task | Priority | Size | Depends on | Files / modules | Acceptance criteria | Definition of done |
|---|---|---|---|---|---|---|---|
| **P1-01** | Make scans recoverable instead of permanently blocking | **Critical** | M | P0-01 | `app/Jobs/RunScanJob.php`, `app/Models/IdentityProfile.php`, `app/Services/ScanOrchestrator.php`, new `scans:reap-stale` command | `$tries` raised and a `failed()` handler marks the scan `failed`; a scheduled reaper fails scans with no heartbeat for > N minutes; `activeScan()` ignores stale scans; users can always start a new scan | Tests cover: worker missing, timeout, hard kill, malformed payload, and reaper recovery; no path leaves a scan non-terminal indefinitely |
| **P1-02** | Label demo data on every surface | **Critical** | S | P0-01 | `dashboard.blade.php`, `findings-index.blade.php`, `credential-exposures.blade.php`, `reports-page.blade.php`, `reports/download.blade.php` | Every finding/credential/report rendered from demo data shows a visible `DEMO` badge; seeded findings carry `verification_status='demo'` | A user cannot mistake demo output for a real check; test asserts the demo badge renders for demo rows |
| **P1-03** | Stop the credentials page from asserting "all clear" | **Critical** | S | P0-01 | `livewire/credential-exposures.blade.php`, `app/Livewire/Credentials/CredentialExposures.php` | When no breach provider is configured the page says exactly that, and no "clear" language is shown; the dashboard panel does the same | Copy reviewed against `PRODUCT.md` principles; test covers the unconfigured state |
| **P1-04** | Fix report download URLs and decide the signature's fate | **High** | S | P0-01 | `app/Models/Report.php`, `app/Services/ReportService.php`, `app/Events/ReportGenerated.php`, `reports/download.blade.php` | `downloadUrl()` returns a signed URL; the `ReportGenerated` payload carries a working link; the rendered signature is either verified on download or removed | Tests: signed URL works, tampered token rejected, broadcast payload resolves |
| **P1-05** | Fix dead links and dead ends | **High** | S | P0-01 | `settings-page.blade.php:78`, `landing.blade.php`, `routes/web.php` | "Compare plans" lands on a real pricing section (or is removed until P3-01); every `href="#…"` resolves; both `/monitoring` and `/alerts` behave consistently | Link check script/test over rendered HTML; zero broken anchors |
| **P1-06** | Remove or substantiate every security claim | **High** | S | P0-01 | `layouts/app.blade.php:45`, `pages/security.blade.php:39`, `livewire/settings-page.blade.php:83`, `pages/privacy.blade.php` | "ENCRYPTED", "validated and encrypted", "PCI-compliant provider" and "withdraw consent by deleting the identity" are removed until true; `TR-782` removed or derived from the profile | Copy audit recorded; no claim in the UI lacks an implementing feature |
| **P1-07** | Restore the scan-progress screen | **High** | M | P1-01 | `resources/views/livewire/scan-progress.blade.php`, `app/Livewire/Scans/ScanProgress.php` | Screen shows current stage, real source progress, findings discovered so far, a working cancel with confirmation, a clear failed state with retry, and a completed state linking to results | Manual check of queued/running/completed/failed/cancelled; component test for cancel; `object-fit-cover` typo fixed |
| **P1-08** | Repair identity setup and identity index controls | **High** | M | P0-01 | `identity-setup.blade.php`, `IdentitySetup.php`, `identity-index.blade.php` | `removeUsername` resolves (or chips use `removeChip`); `previous_names`, `aliases`, email/phone/URL chips are reachable; adding/removing identifiers has tests | No `wire:click` in any view targets a missing method (automated check); round-trip edit test |
| **P1-09** | Restore findings list affordances | **Medium** | S | P0-01 | `findings-index.blade.php`, `FindingsIndex.php` | Status filter, sort and category filter render and work against real `Finding` enum values; each row links to `findings.show` | Filter matrix tested; no filter value yields a permanently empty list |
| **P1-10** | Dead-code and artefact cleanup | **Medium** | S | P0-01 | `welcome.blade.php`, `NotificationsPanel`, `OtpService::purgeExpired`, unused model scopes, `.tmp_ping.php`, `.phpunit.result.cache`, stale `storage/framework/views/*`, the audit probe file | Dead views/classes removed; unused public methods either wired or deleted; scratch files removed; compiled-view cache cleared | Grep proves no route/view reference to removed files; app boots and suite is green |

---

## PHASE 2 — Core Product Completion

**Goal:** close the seams between what the product promises and what the pipeline does.
**Exit criteria:** every advertised capability either works end-to-end or is absent from the product surface.

| ID | Task | Priority | Size | Depends on | Files / modules | Acceptance criteria | Definition of done |
|---|---|---|---|---|---|---|---|
| **P2-01** | Complete the identity lifecycle | **High** | M | P1-08 | `IdentityProfilePolicy`, `IdentityIndex`, `ConsentService`, migrations | User can archive/delete an identity; deletion cascades findings/scans/removals/reports with an audit entry; consent can be withdrawn and blocks new scans immediately | Tests for delete + cascade + consent withdrawal; privacy page copy matches reality |
| **P2-02** | Make live scanning operable and explainable | **High** | M | P1-01, P1-07 | `ScanOrchestrator`, `ScanCreate`, `ScanProgress`, `PythonScannerClient` | Preflight blocks a live scan with no monitored username and says why; per-source outcomes (`found`/`not_found`/`unknown`/`skipped`) are visible; worker-unavailable is a specific, actionable error; retry is one click | Failure-mode tests green; a live scan against a known username produces evidence rows the user can inspect |
| **P2-03** | Finish the removal workflow | **High** | L | P2-01 | `ScanOrchestrator:509-519`, `RemovalWorkflowService`, `RemovalTask`, removal views | Reappearance creates a new task and closes the old one consistently; unique constraints prevent duplicate tasks; removal statuses map 1:1 to the brief's eight states; "automatic" is either implemented or removed from copy | State-machine test matrix; no alert claims an action that was not taken |
| **P2-04** | Decide and close the credential-monitoring story | **Critical (decision)** | M/L | P0-06 | `config/traceless.php`, `CredentialExposures`, `ScanOrchestrator:459-463`, nav, landing | Decision recorded; either (a) a breach provider is integrated with consent + evidence, or (b) the feature is removed from navigation/marketing/dashboard and its tables retained for later | If integrated: provider tests + rate limits + disclosure. If removed: zero references in UI; docs updated |
| **P2-05** | Make monitoring real | **High** | M | P2-01 | `DispatchDueMonitoringScans`, `MonitoringPage`, `ScanOrchestrator:521-544` | Live scans raise alerts for new/reappeared findings (not only demo/critical); dispatcher honours plan entitlement, handles `next_scan_at IS NULL`, backs off on failure; schedule has `withoutOverlapping` | Dispatcher tests with due/not-due/failing/NULL rows; alert produced on a live-simulated scan |
| **P2-06** | Deliver notifications the user can actually receive | **Medium** | M | P2-05 | `app/Notifications/*`, `NotificationsPanel`, `layouts/app.blade.php` | Alert and scan-complete notifications include the `mail` channel (respecting user preference); the in-app panel is mounted in the shell or deleted | Notification fake tests for both channels; panel visible and keyboard-operable |
| **P2-07** | Differentiate Deep Scan (or rename it) | **High** | L | P2-02 | `ScanOrchestrator`, `ScanCreate`, `ReportService`, `config/traceless.php` | Deep Scan genuinely differs: wider source set, multiple profiles, advanced report, recurring monitoring entitlement; otherwise the product exposes a single plan | Documented capability matrix; tests proving deep ≠ snapshot behaviour |
| **P2-08** | Human review for high-risk findings | **Medium** | L | P2-07 | new review queue, `Finding`, admin surface | Critical findings can be reviewed/annotated before being presented as verified; reviewer identity audited | Review state visible on the finding; audit entry per decision |
| **P2-09** | Remove or wire the dead Python modules | **Low** | S | P2-02 | `python-services/normalizer/normalizer.py`, `python-services/local-ai/safety_engine.py`, `config/traceless.php` | Either both modules are invoked and tested from the pipeline, or they are deleted along with `config('traceless.python.ai')` | No orphan module claims capability the pipeline does not use; README architecture notes corrected |
| **P2-10** | Scan pacing and notification batching | **Medium** | S | P1-01 | `ScanOrchestrator::delay()`, alert creation, `config/traceless.php` | Artificial `stage_delay_ms` sleeps removed from production (visual pacing stays client-side if desired); critical-finding alerts batched per scan instead of one per finding | Scan wall-clock measured before/after; a large scan produces one digest alert, not N |

---

## PHASE 3 — Monetisation & Entitlements

**Goal:** either sell the paid tier properly or launch without one — deliberately.
**Exit criteria:** no user can obtain a paid capability without an entitlement record that came from a real payment (or an explicit admin grant), and the UI never promises checkout that does not exist.

| ID | Task | Priority | Size | Depends on | Files / modules | Acceptance criteria | Definition of done |
|---|---|---|---|---|---|---|---|
| **P3-01** | Decide the commercial model and publish pricing | **Critical** | M | P0-06 | `landing.blade.php` (pricing section), `pages/terms.blade.php`, `config/traceless.php` | Decision: free-only launch / manual invoicing / gateway. Pricing page reflects the decision; `config('traceless.plans')` is either used or deleted | Decision logged; pricing section live; terms consistent |
| **P3-02** | Integrate a payment provider | **Critical** | L | P3-01 | new `app/Services/Billing/*`, `Subscription`, `BillingRecord`, webhook route | Hosted checkout; webhook signature verification; subscription states (active/past_due/cancelled) drive entitlement; customer portal for cancellation | Sandbox purchase → entitlement; cancellation → downgrade at period end; webhook replayed safely |
| **P3-03** | Single source of truth for entitlements | **High** | M | P3-02 | `User::isDeepScan()`, all call sites; remove `enableDemoDeepScan` from production paths | One entitlement service, used by scan creation, reports, monitoring, dispatcher; demo grant compiled out or env-fenced | Grep proves one authority; tests per gated capability |
| **P3-04** | Billing records, receipts and dunning | **High** | L | P3-02 | `BillingRecord`, settings page, mail | Every charge recorded; receipts emailed; failed payment surfaces in-app and by email; retry/dunning path exists | Sandbox lifecycle test; receipt mail asserted |
| **P3-05** | Align terms, refunds and cancellation copy | **Medium** | S | P3-01 | `pages/terms.blade.php`, settings | Refund/cancellation wording matches the provider's actual behaviour | Legal review sign-off recorded |

---

## PHASE 4 — Security & Privacy Hardening

**Goal:** make the security posture match the product's promises.
**Exit criteria:** enforceable authorization, encryption at rest for personal data, no OTP/PII in logs, complete retention and deletion, and standard browser hardening.

| ID | Task | Priority | Size | Depends on | Files / modules | Acceptance criteria | Definition of done |
|---|---|---|---|---|---|---|---|
| **P4-01** | Enforce authorization through policies | **High** | M | P0-01 | `app/Policies/*`, all Livewire components | Every resource read/write goes through `authorize()`/`Gate`; inline checks removed or delegated; IDOR matrix test added | Policy coverage test enumerates component × resource × other-user and asserts 403 |
| **P4-02** | Encrypt personal data at rest | **High** | M | P0-03 | `IdentityProfile`, `PublicIdentifier`, `CredentialExposure`, `RemovalTask.notes`, migrations | Identifiers, phone numbers, notes and evidence hashes are encrypted casts or envelope-encrypted; key rotation documented | Migration backfills safely; queries that need plaintext have an indexable strategy; tests cover encrypt/decrypt |
| **P4-03** | Redact sensitive data from logs | **High** | S | P0-01 | `config/logging.php`, `OtpService`, `PythonScannerClient`, `ScanOrchestrator` | No OTP plaintext, no email bodies, no full URLs with tokens, no PII in log context; a processor scrubs known keys | Log-sample test asserts no code/email appears; `storage/logs` contains no OTP bodies after a full auth run |
| **P4-04** | Complete data retention | **High** | M | P0-01 | `PruneTraceLessData`, migrations, `config/traceless.php` | Findings are never orphaned by pruning; credentials, alerts, consent and removal tasks have retention rules; retention writes an audit record; indexes support the queries | `--dry-run` and real run tested; no orphan rows after prune; audit entry per run |
| **P4-05** | Complete deletion and export | **High** | M | P4-04 | `SettingsPage`, `AccountExportController`, `audit_logs` | Deletion removes personal data, pseudonymises audit IPs, retains consent proof as required, and completes within a stated SLA; export includes every category | Deletion test asserts no personal identifiers remain; export reviewed against a data map |
| **P4-06** | Browser and transport hardening | **High** | S | P0-02 | `bootstrap/app.php`, new middleware, `config/session.php` | CSP (no inline script beyond Livewire/Alpine needs), HSTS, `X-Frame-Options`/`frame-ancestors`, `Referrer-Policy`, `Permissions-Policy`; secure/HttpOnly/SameSite cookies; HTTPS enforced | Header test per route; CSP violation report endpoint or documented report-only phase |
| **P4-07** | Rate limiting and abuse prevention | **Medium** | S | P4-01 | `routes/web.php`, Livewire forms, `RateLimiter` | Account-change OTPs rate-limited; scan creation quota per plan; verification attempts limited per IP and per address | Throttle tests; documented limits in help/FAQ |
| **P4-08** | Secret hygiene and rotation | **High** | S | P0-03 | `.env`, compose, hosting config | All secrets rotated; `.env` never baked into images; compose no longer reads a developer `.env`; secrets stored in the platform's secret manager | Rotation record; compose uses `env_file: .env.production` or platform injection; `git grep` finds no credential |
| **P4-09** | Source-restriction and scan-safety review | **Medium** | M | P2-02 | `python-services/scanner/worker.py` | Worker re-reviewed for SSRF, redirect handling, host allow-list, robots/ToS posture, and per-host politeness; findings documented | Written safety review; worker tests for redirect-to-private-IP and oversize body |

---

## PHASE 5 — UI/UX Transformation (finish the in-flight redesign)

**Goal:** finish the pass that stopped at 02:42 — not start a new one.
**Exit criteria:** every screen in one visual world, no legacy shim, no light surface on dark, responsive and accessible, and an Impeccable finish review recorded.

| ID | Task | Priority | Size | Depends on | Files / modules | Acceptance criteria | Definition of done |
|---|---|---|---|---|---|---|---|
| **P5-01** | Close the Impeccable pass properly | **High** | M | P0-06 | `.impeccable/briefs/home.md`, new `DESIGN.md` | `DESIGN.md` generated from the implemented code (tokens, components, motion, states); finish review and verdict recorded as the brief requires; asset provenance listed | `DESIGN.md` committed; brief marked complete; provenance for both videos |
| **P5-02** | Unify the component vocabulary | **High** | L | P1-07..P1-09 | all 20 legacy views, `app.css:274-480`, `tailwind.config.js:21-37` | Every view uses the single new vocabulary (`ops-*`/`btn-ops-*` or a consolidated set); legacy shim and legacy colour aliases deleted | Grep for legacy classes returns zero; deleting the shim changes no rendering |
| **P5-03** | Eliminate light-on-dark surfaces | **High** | M | P5-02 | the 11 affected views (see audit **U-02**) | Zero `bg-white/30+`, `bg-white`, `hover:bg-white` inside dark surfaces; all text/background pairs meet WCAG AA | Automated contrast check in CI; visual review at 390/768/1440 |
| **P5-04** | Responsive pass | **High** | M | P5-02 | all views, both layouts | 390 / 768 / 1024 / 1440 verified for every screen; no horizontal scroll; tables adapt; mobile nav is keyboard/Escape safe | Screenshot set per breakpoint stored with the release |
| **P5-05** | Accessibility pass | **High** | M | P5-03 | all views, components | Keyboard-operable everything, visible focus, labels/`aria` on all controls, heading order, no colour-only status, reduced-motion honoured | axe (or equivalent) clean on all routes; manual keyboard walkthrough recorded |
| **P5-06** | Motion and media performance | **Medium** | M | P5-04 | `landing.blade.php`, `scan-progress.blade.php`, `public/assets/videos` | Video poster images, `preload` strategy, size-appropriate encodes, no layout shift, intro never replays, processing video used only for genuinely long operations | Lighthouse/vitals budget met; no autoplay of the intro after first visit |
| **P5-07** | Error and empty states in the new world | **Medium** | S | P5-02 | `resources/views/errors/*`, component states | 403/404/419/429/500/503 designed; every list has a designed empty state; every async action has a designed error state | All states reachable and reviewed |
| **P5-08** | Design QA against real data | **High** | M | P5-03 | whole app | Reviewed with long names, many findings, zero findings, failed scans, demo mode, and live mode; no truncation or overlap | Review notes closed; regression screenshots stored |

---

## PHASE 6 — Testing & QA

**Goal:** close the blind spots the audit found, and make the suite trustworthy in CI.
**Exit criteria:** every critical journey covered end-to-end; the IDOR matrix automated; the scan failure modes tested; CI green on every push.

| ID | Task | Priority | Size | Depends on | Files / modules | Acceptance criteria | Definition of done |
|---|---|---|---|---|---|---|---|
| **P6-01** | Fill backend test gaps | **High** | M | P1-01..P1-09 | `tests/Feature`, `tests/Unit` | Tests exist for monitoring dispatch, retention prune, broadcast events, channel authorization, report URL signing, boot guard, and stale-scan reaper | Coverage of the services layer recorded; every Phase 1/2 fix has a test |
| **P6-02** | Component-level Livewire tests | **Medium** | M | P6-01 | all 15 components | Each component's actions, validation, ownership and error states are tested with Livewire's test helpers | Component matrix complete; no component untested |
| **P6-03** | End-to-end browser tests for the six journeys | **High** | L | P5-04 | new E2E suite (Playwright or Dusk) | Register → verify → identity → consent → scan → finding → report; login; cancel scan; delete account; upgrade/downgrade; monitoring preference | E2E green in CI against a seeded environment |
| **P6-04** | Security testing | **High** | M | P4-01 | test suite + manual | IDOR matrix, OTP abuse/lockout, rate limits, header presence, CSP, open redirects, signed-URL expiry, mass assignment, XSS on every user-supplied field | Findings closed or risk-accepted in writing |
| **P6-05** | Scan failure-mode tests | **High** | M | P1-01 | `tests/Feature` | Worker absent, worker timeout, worker crash, malformed JSON, partial sources, cancellation mid-loop, reaper recovery | Every failure leaves a terminal, explainable state |
| **P6-06** | Performance baseline | **Medium** | M | P5-06 | app + worker | Documented budgets: p95 page render, scan wall-clock for N sources, dashboard query count; no N+1 on primary pages | Budgets met and asserted in a benchmark script |
| **P6-07** | Accessibility verification | **Medium** | M | P5-05 | E2E + manual | Keyboard-only run of all journeys; screen-reader pass on scan/findings/settings; automated axe in CI | Report stored; blockers fixed |

---

## PHASE 7 — Production Infrastructure

**Goal:** a real deployment with domain, TLS, monitoring, backups and a rollback path.
**Exit criteria:** one command deploys a tagged commit; metrics and errors are visible; a restore has been rehearsed.

| ID | Task | Priority | Size | Depends on | Files / modules | Acceptance criteria | Definition of done |
|---|---|---|---|---|---|---|---|
| **P7-01** | Host, domain, TLS | **Critical** | M | P0-03 | DNS, host config | Production host chosen; `APP_URL` set; TLS with auto-renewal; www/apex decided; HSTS after verification | `https://` serves the app; certificate expiry monitored |
| **P7-02** | Production data services | **Critical** | M | P0-03 | Neon, Redis | Dedicated production Postgres (not the dev branch) and Redis; pooling configured; migrations run as a release step; connection limits sized | `migrate:status` clean in production; a restore from a branch/PITR point verified |
| **P7-03** | Transactional email | **Critical** | M | P7-01 | Resend/SMTP, DNS | Sending domain verified with SPF/DKIM/DMARC; `MAIL_FROM_ADDRESS` on-domain; OTP deliverability tested to Gmail/Outlook/Yahoo; bounce handling | A real signup receives a code in under 60 s; DMARC pass recorded |
| **P7-04** | Deployment pipeline and rollback | **Critical** | L | P0-01 | `.github/workflows/ci.yml`, image registry, deploy script | CI: PHP + Python tests, style, build, image. CD: tagged image → release → migrate → restart queue/scheduler; previous image retained for rollback | Deploy and rollback both rehearsed on staging; deploy takes < 10 min |
| **P7-05** | Monitoring, alerting, error tracking | **Critical** | M | P7-04 | observability stack | Uptime check on `/up`, error tracker, queue depth, failed-job alert, slow-scan alert, disk/DB alerts; on-call destination defined | A synthetic failure triggers an alert to a human |
| **P7-06** | Backups and restore drill | **Critical** | M | P7-02 | DB + object storage | Automated backups; documented RPO/RTO; a restore into a scratch environment executed and timed | Restore report stored; retention window recorded in the runbook |
| **P7-07** | Log aggregation and retention | **High** | S | P7-04 | logging config | Structured logs to the platform; retention set; PII redaction verified in production logs | Log search works; no PII found in a sample |
| **P7-08** | Edge protection | **Medium** | M | P7-01 | CDN/WAF | WAF/rate limits, bot protection on auth routes, static asset caching, video delivered from CDN | Attack simulation rate-limited; video served with cache headers |
| **P7-09** | Queue and scheduler supervision | **High** | S | P7-04 | compose/host services | Queue workers restarted after deploy; dead-worker alert; scheduler heartbeat monitored | Kill a worker → alert fires; deploy restarts workers |
| **P7-10** | Staging environment identical to production | **High** | M | P7-04 | infra | Staging runs the production image with production-like env (demo off) and a separate DB | Staging smoke test green before each release |

---

## PHASE 8 — Launch Preparation

**Goal:** the legal, support and verification work that turns a deployment into a launch.
**Exit criteria:** go/no-go review passed with evidence; rollback plan agreed.

| ID | Task | Priority | Size | Depends on | Files / modules | Acceptance criteria | Definition of done |
|---|---|---|---|---|---|---|---|
| **P8-01** | Legal and compliance pack | **Critical** | M | P3-01 | `pages/terms`, `pages/privacy`, new DPA/subprocessors | Operating entity and contact in the footer; ToS reviewed; privacy policy matches the data map; subprocessor list (Neon, Upstash, Resend, host) published; lawful basis for scanning recorded; DPIA note | Counsel sign-off recorded; pages dated and versioned |
| **P8-02** | First-run onboarding | **High** | M | P5-08 | identity setup, dashboard empty state, help copy | New user knows the next action within one screen; a sample/demo identity is clearly optional; "what happens next" explained before the first scan | Usability walkthrough with 3 users; drop-off points recorded |
| **P8-03** | Support and status | **Medium** | M | P7-05 | contact route, help docs | Support address and response expectation published; status page links to uptime monitor; help content for consent, OTP, removal limits, deletion | Links live and tested |
| **P8-04** | Subscription verification (if monetised) | **Critical** | M | P3-02 | billing | End-to-end purchase, renewal, cancellation, refund, failed-payment email; tax handling decision recorded | Sandbox and production-mode smoke both pass |
| **P8-05** | Production smoke test | **Critical** | S | P7-10, P8-04 | runbook | The README verification checklist executed on production-like infra with demo off and a real mailbox | Signed smoke-test record per release |
| **P8-06** | Go/no-go review and rollback plan | **Critical** | S | all | runbook | Launch checklist reviewed; each item evidenced; rollback and incident owner named | Written go decision with named approver |
| **P8-07** | Soft launch and telemetry review | **High** | M | P8-06 | analytics, alerts | Cohort invited; error rate, scan success rate, OTP delivery, activation and removal completion reviewed daily for two weeks | Metrics dashboard live; first post-launch review held |

---

## Milestone schedule (indicative)

| Milestone | Phases | Calendar | Exit gate |
|---|---|---|---|
| **M0 — Safe baseline** | Phase 0 | Week 1 | Tagged repo; boot guard; environments split; build current |
| **M1 — Honest product** | Phase 1 | Weeks 2–3 | No dead-end scans; demo labelled; no false claims; every visible control works |
| **M2 — Complete journeys** | Phase 2 | Weeks 3–5 | Scan → findings → removal → monitoring works live; credential decision executed |
| **M3 — Monetised (or deliberately free)** | Phase 3 | Weeks 4–6 | Entitlements from a real source; no fake checkout |
| **M4 — Hardened** | Phase 4 | Weeks 5–7 | Policies enforced, data encrypted, retention complete, headers present |
| **M5 — Designed** | Phase 5 | Weeks 3–6 (parallel with 2–4, after Phase 1) | One visual world, responsive, accessible, `DESIGN.md` |
| **M6 — Verified** | Phase 6 | Weeks 6–8 | CI green, E2E journeys, security matrix, failure modes |
| **M7 — Deployed** | Phase 7 | Weeks 7–9 | Domain, TLS, monitoring, backups, rollback rehearsed |
| **M8 — Launched** | Phase 8 | Week 9 | Go/no-go passed; smoke test recorded |

Parallelism that is safe: Phase 5 (design) can run alongside Phases 2–4 once Phase 1 is merged, provided the design work is limited to the 20 legacy views and does not touch Livewire component logic. Phase 6 runs continuously from M1 onward.

Unsafe parallelism: Phase 3 and Phase 4 both rewrite entitlement/authorization call sites — sequence them. Phase 2 and Phase 5 both touch scan-progress and identity screens — merge Phase 1 first.

---

## Explicitly out of scope until after launch

Recorded so the plan does not drift:

- **Automated data-broker removal** (the "automated request" engine). Launch with guided manual removal; automate when volume justifies it.
- **Reverb/WebSocket real-time.** Livewire polling is adequate; revisit when scan volume makes polling expensive.
- **PDF report export.** Self-contained HTML downloads are acceptable for launch.
- **Multi-user teams / seats.** The data model is per-user; a teams feature is a different product.
- **Native mobile apps.**
- **Broader internationalisation.** English-only at launch; the copy is already plain.
- **Self-hosted scanner cluster.** One worker process is enough until concurrency demands more.

---

## Risk register (top five)

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Another agent edits the tree while the plan is executing, and changes are lost | **High** (observed during the audit) | High | P0-01 first; one writer at a time; branch per task; CI as the merge gate |
| Production launches with demo mode or OTP display still on | Medium | **Critical** | P0-02 boot guard + a smoke test that asserts both are off |
| The redesign is restarted instead of finished | Medium | High | P5 is explicitly a *finish* pass anchored to `PRODUCT.md` + the surface brief; the shim is deleted only after P5-02 |
| Paid tier is built on an undecided pricing model and reworked twice | Medium | High | P3-01 decision gate before any billing code |
| Credential monitoring ships as "all clear" and becomes a trust incident | Medium | **Critical** | P1-03 immediately; P2-04 decision before launch |
