# TraceLess — Master Project TODO

**This is the single source of truth for outstanding work.**
Every task below appears exactly once. Acceptance criteria and file-level detail live in `TRACELESS_LAUNCH_ROADMAP.md` (same task ID); this file is the tracking layer.

| | |
|---|---|
| **Baseline snapshot** | 2026-11-14 02:42 (frozen) |
| **Audit** | `TRACELESS_PROJECT_AUDIT.md` |
| **Roadmap** | `TRACELESS_LAUNCH_ROADMAP.md` |
| **Total tasks** | 72 + 4 open decisions |
| **Complete** | 0 / 72 |
| **Current milestone** | M0 — Safe baseline (Phase 0) |

**Status legend:** `[ ]` not started · `[~]` in progress · `[x]` done · `[!]` blocked (add a note beneath) · `[-]` dropped (record why)

**Rules for this tracker**

1. One owner per task; claim it before starting.
2. Never tick a task unless the roadmap's *Definition of done* is evidenced (test, screenshot, recorded command output, or review note).
3. New work gets a new ID in the correct phase. Do not reopen a ticked task — add a follow-up.
4. Order is dependency order, not preference. Do not start a task whose *Depends* rows are unticked.
5. Keep the progress counters at the top current.

---

## Start here — the next five actions

*(Pointers only — the checkbox for each lives in Phase 0.)*

1. **P0-01** — Create the Git baseline (everything else is unsafe until this exists)
2. **P0-02** — Remove `/__preview/dashboard` and add the production boot guard
3. **P0-03** — Move local development off the Neon `production` branch
4. **P0-04** — Rebuild front-end assets from the 02:42 snapshot
5. **P0-05** — Document and verify the writable-storage test invocation

---

## Open decisions (block their downstream tasks)

- [ ] **D-01** — Launch model: free-only, manual invoicing, or paid via gateway · blocks **P3-01**, **P3-02**, **P8-04**
- [ ] **D-02** — Credential monitoring in launch scope: integrate a provider or remove the feature · blocks **P2-04**, **P5-08**, **P8-01**
- [ ] **D-03** — Host, domain, DNS and transactional-email provider · blocks **P7-01**, **P7-03**
- [ ] **D-04** — Deployment topology: single host running app + scanner, or scanner on a separate host · blocks **P7-04**, **P7-09**

Record each decision (choice, date, owner, consequence) in `docs/DECISIONS.md` as part of **P0-06**.

---

## Phase 0 — Baseline & Recovery (M0)

**Goal:** safe to change. **Exit:** tagged repo, no auth bypass, fail-closed production config, fresh build.

- [ ] **P0-01** — Git baseline & repository hygiene · **Critical** · S · *Depends:* — · *Done when:* root repo exists, vendored upstream `.git` excluded, secrets/vendor ignored, first commit tagged `baseline-2026-11-14`, and a clean clone boots from `.env.example`
- [ ] **P0-02** — Remove preview auto-login + production boot guard · **Critical** · S · *Depends:* P0-01 · *Done when:* the route is deleted and the app throws when production runs with debug/demo/OTP-display enabled, with a test per combination
- [ ] **P0-03** — Separate dev from the production database branch · **Critical** · S · *Depends:* P0-01 · *Done when:* local dev uses SQLite or a dedicated Neon branch and the branch strategy is documented
- [ ] **P0-04** — Rebuild assets and verify the frozen snapshot · **High** · S · *Depends:* P0-01 · *Done when:* `npm ci && npm run build` succeeds, the manifest updates, and all public routes return 200/302
- [ ] **P0-05** — Repeatable test invocation · **High** · S · *Depends:* P0-01 · *Done when:* a documented command runs 50/50 PHP + 14/14 Python tests from a clean clone
- [ ] **P0-06** — Commit audit, roadmap, tracker and decision log · **Medium** · S · *Depends:* P0-01 · *Done when:* the three documents are in the repo, `docs/DECISIONS.md` is seeded with D-01…D-04, and the README links them

---

## Phase 1 — Truth & Trust Pass (M1)

**Goal:** honest and unblocked. **Exit:** no dead-end scan, demo clearly labelled, no unverifiable claim, every visible control works.

- [ ] **P1-01** — Scan failure recovery · **Critical** · M · *Depends:* P0-01 · *Done when:* retries + `failed()` + a stale-scan reaper exist, `activeScan()` ignores stale rows, and users can always start a new scan
- [ ] **P1-02** — Demo labelling on every surface · **Critical** · S · *Depends:* P0-01 · *Done when:* dashboard, findings list, credentials and reports show a `DEMO` badge for demo rows, and seeded findings carry `verification_status='demo'`
- [ ] **P1-03** — Honest credential empty state · **Critical** · S · *Depends:* P0-01 · *Done when:* a no-provider config shows "provider not configured" and no "clear/all clear" copy remains anywhere
- [ ] **P1-04** — Report download URLs + signature decision · **High** · S · *Depends:* P0-01 · *Done when:* `Report::downloadUrl()` is signed, the broadcast payload carries a working link, and the signature is either verified or removed
- [ ] **P1-05** — Dead links and dead ends · **High** · S · *Depends:* P0-01 · *Done when:* `#pricing` resolves or the link is removed, every `#anchor` exists, and `/alerts` and `/monitoring` behave consistently
- [ ] **P1-06** — Remove or substantiate security claims · **High** · S · *Depends:* P0-01 · *Done when:* "ENCRYPTED", "validated and encrypted", "PCI-compliant", "withdraw by deleting the identity" and `TR-782` are gone or true
- [ ] **P1-07** — Restore the scan-progress screen · **High** · M · *Depends:* P1-01 · *Done when:* stage, real source progress, findings so far, cancel with confirm, failed state with retry, and completed state with next step all render; `object-fit-cover` fixed
- [ ] **P1-08** — Repair identity setup + index controls · **High** · M · *Depends:* P0-01 · *Done when:* no `wire:click` targets a missing method and previously unreachable fields (previous names, aliases, email/phone/URL chips) work
- [ ] **P1-09** — Restore findings list affordances · **Medium** · S · *Depends:* P0-01 · *Done when:* status/sort/category filters work against real enum values and every row links to its detail page
- [ ] **P1-10** — Dead-code and artefact cleanup · **Medium** · S · *Depends:* P0-01 · *Done when:* unused view/component/scopes are removed, `.tmp_ping.php` and caches are cleaned, and the app boots with a green suite

---

## Phase 2 — Core Product Completion (M2)

**Goal:** advertised capability works end to end, or is not advertised. **Exit:** live scan → findings → removal → monitoring is coherent; the credential decision is executed.

- [ ] **P2-01** — Identity lifecycle · **High** · M · *Depends:* P1-08 · *Done when:* an identity can be archived/deleted with cascades and audit, consent can be withdrawn, and new scans are blocked immediately after withdrawal
- [ ] **P2-02** — Live scanning operable and explainable · **High** · M · *Depends:* P1-01, P1-07 · *Done when:* preflight requires a username, per-source outcomes are visible, and worker failures are specific and retryable
- [ ] **P2-03** — Finish the removal workflow · **High** · L · *Depends:* P2-01 · *Done when:* reappearance creates a new task and closes the old, duplicates are impossible, the eight statuses map 1:1, and "automatic" is either real or absent from copy
- [ ] **P2-04** — Credential-monitoring decision executed · **Critical** · M/L · *Depends:* D-02 · *Done when:* either a provider is integrated with consent/evidence/limits, or the feature is removed from navigation, dashboard, landing and settings
- [ ] **P2-05** — Monitoring that actually alerts · **High** · M · *Depends:* P2-01 · *Done when:* live scans alert on new/reappeared findings, the dispatcher honours entitlement and `next_scan_at IS NULL`, and failures back off
- [ ] **P2-06** — Notifications the user can receive · **Medium** · M · *Depends:* P2-05 · *Done when:* alert and scan-complete use the mail channel (respecting preferences) and the in-app panel is mounted or deleted
- [ ] **P2-07** — Differentiate Deep Scan (or rename) · **High** · L · *Depends:* P2-02 · *Done when:* deep genuinely differs (sources, profiles, report, monitoring) or the product exposes a single plan
- [ ] **P2-08** — Human review for high-risk findings · **Medium** · L · *Depends:* P2-07 · *Done when:* critical findings can be reviewed/annotated before being presented, with reviewer and timestamp audited
- [ ] **P2-09** — Remove or wire the dead Python modules · **Low** · S · *Depends:* P2-02 · *Done when:* `normalizer.py` / `safety_engine.py` are invoked and tested, or deleted together with their config key and README claim
- [ ] **P2-10** — Scan pacing + notification batching · **Medium** · S · *Depends:* P1-01 · *Done when:* artificial worker sleeps are gone from production and a large scan emits one digest alert rather than N

---

## Phase 3 — Monetisation & Entitlements (M3)

**Goal:** sell properly or launch deliberately free. **Exit:** entitlement only from a real source; no fake checkout.

- [ ] **P3-01** — Commercial model + pricing page · **Critical** · M · *Depends:* D-01 · *Done when:* the model is recorded, the pricing section exists, and `config('traceless.plans')` is used or deleted
- [ ] **P3-02** — Payment provider integration · **Critical** · L · *Depends:* P3-01 · *Done when:* hosted checkout, verified webhooks, subscription states and a cancellation portal all work in sandbox
- [ ] **P3-03** — Single entitlement authority · **High** · M · *Depends:* P3-02 · *Done when:* one service decides entitlements for scans, reports, monitoring and the dispatcher, and the demo self-grant is unreachable in production
- [ ] **P3-04** — Billing records, receipts, dunning · **High** · L · *Depends:* P3-02 · *Done when:* every charge is recorded, receipts are emailed, failed payment is surfaced, and a retry path exists
- [ ] **P3-05** — Terms, refunds, cancellation copy · **Medium** · S · *Depends:* P3-01 · *Done when:* legal wording matches provider behaviour and is signed off

---

## Phase 4 — Security & Privacy Hardening (M4)

**Goal:** posture matches promises. **Exit:** enforced authorization, encrypted personal data, clean logs, complete retention and deletion, browser hardening.

- [ ] **P4-01** — Enforce authorization through policies · **High** · M · *Depends:* P0-01 · *Done when:* every resource access goes through `authorize()`/`Gate` and a component × resource × other-user 403 matrix is automated
- [ ] **P4-02** — Encrypt personal data at rest · **High** · M · *Depends:* P0-03 · *Done when:* identifiers, phones, notes and evidence are encrypted with a documented key-rotation path and a safe backfill
- [ ] **P4-03** — Redact sensitive data from logs · **High** · S · *Depends:* P0-01 · *Done when:* no OTP plaintext, mail body, token or PII appears in logs, asserted by a log-sample test
- [ ] **P4-04** — Complete data retention · **High** · M · *Depends:* P0-01 · *Done when:* pruning never orphans findings, credentials/alerts/consent/tasks have rules, indexes exist, and each run is audited
- [ ] **P4-05** — Complete deletion and export · **High** · M · *Depends:* P4-04 · *Done when:* deletion removes personal data, pseudonymises audit IPs, keeps required consent proof, meets a stated SLA, and export covers every category
- [ ] **P4-06** — Browser/transport hardening · **High** · S · *Depends:* P0-02 · *Done when:* CSP, HSTS, frame-ancestors, referrer and permissions policies ship, cookies are secure/HttpOnly/SameSite, and HTTPS is enforced
- [ ] **P4-07** — Rate limiting and abuse prevention · **Medium** · S · *Depends:* P4-01 · *Done when:* account-change OTPs, scan quotas and verification attempts are limited, registration no longer enumerates, and limits are documented
- [ ] **P4-08** — Secret hygiene and rotation · **High** · S · *Depends:* P0-03 · *Done when:* all secrets are rotated and held in the platform secret store, images carry no `.env`, and compose no longer reads a developer file
- [ ] **P4-09** — Scanner safety review · **Medium** · M · *Depends:* P2-02 · *Done when:* SSRF/redirect/allow-list/politeness are reviewed in writing with tests for redirect-to-private-IP and oversize bodies

---

## Phase 5 — UI/UX Transformation Finish (M5)

**Goal:** finish the in-flight redesign. **Exit:** one visual world, no shim, no light-on-dark, responsive, accessible, finish review recorded.

- [ ] **P5-01** — Close the Impeccable pass · **High** · M · *Depends:* P0-06 · *Done when:* `DESIGN.md` exists from implemented code, the finish review and verdict are recorded, and asset provenance is listed
- [ ] **P5-02** — Unify the component vocabulary · **High** · L · *Depends:* P1-07, P1-08, P1-09 · *Done when:* all 20 legacy views use the single vocabulary and the legacy shim/aliases are deleted with no visual change
- [ ] **P5-03** — Eliminate light-on-dark surfaces · **High** · M · *Depends:* P5-02 · *Done when:* zero `bg-white/30+`-on-dark remains across the 11 affected views and all text pairs pass WCAG AA
- [ ] **P5-04** — Responsive pass · **High** · M · *Depends:* P5-02 · *Done when:* 390/768/1024/1440 are verified per screen, with no horizontal scroll and a keyboard/Escape-safe mobile nav
- [ ] **P5-05** — Accessibility pass · **High** · M · *Depends:* P5-03 · *Done when:* keyboard, focus, labels/aria, heading order, non-colour status and reduced motion are verified and automated checks are clean
- [ ] **P5-06** — Motion and media performance · **Medium** · M · *Depends:* P5-04 · *Done when:* posters, preload strategy, tuned encodes, no layout shift, an intro that never replays, and processing video only for long operations
- [ ] **P5-07** — Error and empty states · **Medium** · S · *Depends:* P5-02 · *Done when:* 403/404/419/429/500/503 are designed, every list has an empty state, and every async action has an error state
- [ ] **P5-08** — Design QA on real data · **High** · M · *Depends:* P5-03 · *Done when:* long names, many/zero findings, failed scans, demo and live modes are all reviewed with no truncation or overlap

---

## Phase 6 — Testing & QA (M6)

**Goal:** no blind spots; CI trustworthy. **Exit:** critical journeys covered, IDOR matrix automated, failure modes tested, CI green.

- [ ] **P6-01** — Backend test gaps · **High** · M · *Depends:* P1-01…P1-10 · *Done when:* dispatcher, prune, events, channel auth, report URL signing, boot guard and reaper are all covered
- [ ] **P6-02** — Livewire component tests · **Medium** · M · *Depends:* P6-01 · *Done when:* all 15 components have action/validation/ownership/error tests
- [ ] **P6-03** — End-to-end journeys · **High** · L · *Depends:* P5-04 · *Done when:* six journeys (register→scan→report, login, cancel, delete, upgrade/downgrade, monitoring) run in CI against seeded data
- [ ] **P6-04** — Security testing · **High** · M · *Depends:* P4-01 · *Done when:* IDOR matrix, OTP abuse, throttles, headers, CSP, signed-URL expiry, mass assignment and XSS are tested and findings closed
- [ ] **P6-05** — Scan failure-mode tests · **High** · M · *Depends:* P1-01 · *Done when:* worker absent/timeout/crash, malformed JSON, partial sources, mid-loop cancel and reaper recovery all leave terminal, explainable states
- [ ] **P6-06** — Performance baseline · **Medium** · M · *Depends:* P5-06 · *Done when:* p95 render, scan wall-clock and dashboard query count are budgeted and asserted
- [ ] **P6-07** — Accessibility verification · **Medium** · M · *Depends:* P5-05 · *Done when:* keyboard-only journeys, a screen-reader pass and an automated axe run are recorded

---

## Phase 7 — Production Infrastructure (M7)

**Goal:** real deployment with rollback. **Exit:** a tagged commit deploys in one command; metrics, errors and backups exist; restore rehearsed.

- [ ] **P7-01** — Host, domain, TLS · **Critical** · M · *Depends:* D-03 · *Done when:* `APP_URL` is real, TLS auto-renews, www/apex is decided, and HSTS is enabled after verification
- [ ] **P7-02** — Production data services · **Critical** · M · *Depends:* P0-03 · *Done when:* dedicated Postgres + Redis exist, pooling is sized, migrations are a release step, and a restore point is verified
- [ ] **P7-03** — Transactional email · **Critical** · M · *Depends:* P7-01 · *Done when:* the domain has SPF/DKIM/DMARC, OTP delivery is tested to Gmail/Outlook/Yahoo under 60 s, and bounces are handled
- [ ] **P7-04** — Deployment pipeline and rollback · **Critical** · L · *Depends:* P0-01, D-04 · *Done when:* CI runs PHP + Python tests and builds an image; CD releases, migrates and restarts workers; rollback is rehearsed
- [ ] **P7-05** — Monitoring, alerting, error tracking · **Critical** · M · *Depends:* P7-04 · *Done when:* uptime, errors, queue depth, failed jobs, slow scans and disk/DB alerts reach a human, proven by a synthetic failure
- [ ] **P7-06** — Backups and restore drill · **Critical** · M · *Depends:* P7-02 · *Done when:* automated backups exist, RPO/RTO are documented, and a timed restore into a scratch environment has been performed
- [ ] **P7-07** — Log aggregation and retention · **High** · S · *Depends:* P7-04 · *Done when:* structured logs are searchable, retention is set, and production samples contain no PII
- [ ] **P7-08** — Edge protection · **Medium** · M · *Depends:* P7-01 · *Done when:* WAF/rate limits and bot protection are active on auth routes, and assets and video are CDN-cached
- [ ] **P7-09** — Queue and scheduler supervision · **High** · S · *Depends:* P7-04 · *Done when:* workers restart on deploy, a dead worker alerts, and a scheduler heartbeat is monitored
- [ ] **P7-10** — Staging identical to production · **High** · M · *Depends:* P7-04 · *Done when:* staging runs the production image with demo off and a separate database, and passes the smoke test

---

## Phase 8 — Launch Preparation (M8)

**Goal:** deployment → launch. **Exit:** go/no-go passed with evidence and a rollback plan.

- [ ] **P8-01** — Legal and compliance pack · **Critical** · M · *Depends:* P3-01 · *Done when:* entity and contact are published, ToS/privacy match the data map, subprocessors are listed, and lawful basis plus a DPIA note are recorded
- [ ] **P8-02** — First-run onboarding · **High** · M · *Depends:* P5-08 · *Done when:* a new user knows the next action within one screen and a demo/sample identity is clearly optional
- [ ] **P8-03** — Support and status · **Medium** · M · *Depends:* P7-05 · *Done when:* support address, expectations, status page and help content for consent/OTP/removal/deletion are live
- [ ] **P8-04** — Subscription verification (if monetised) · **Critical** · M · *Depends:* P3-02 · *Done when:* purchase, renewal, cancellation, refund and failed-payment paths pass in sandbox and in a production-mode smoke
- [ ] **P8-05** — Production smoke test · **Critical** · S · *Depends:* P7-10 · *Done when:* the README verification checklist runs on production-like infra with demo off and a real mailbox, recorded per release
- [ ] **P8-06** — Go/no-go review and rollback plan · **Critical** · S · *Depends:* all · *Done when:* every launch-checklist item has evidence, an incident owner is named, and a written go decision is signed
- [ ] **P8-07** — Soft launch and telemetry review · **High** · M · *Depends:* P8-06 · *Done when:* a cohort is invited and error rate, scan success, OTP delivery, activation and removal completion are reviewed daily for two weeks

---

## Audit-issue coverage

Every finding in `TRACELESS_PROJECT_AUDIT.md` §G maps to at least one task. Nothing is lost.

| Audit | Task(s) | Audit | Task(s) |
|---|---|---|---|
| C-01 | P0-01 | S-01 | P0-02 |
| C-02 | P1-01 | S-02 | P0-02 |
| C-03 | P2-07, P3-01, P3-02, P3-03 | S-03 | P1-06 |
| C-04 | P1-03, P2-04 | S-04 | P4-01 |
| C-05 | P0-02 | S-05 | P4-02 |
| C-06 | P0-02 | S-06 | P4-03 |
| F-01 | P2-07 | S-07 | P4-05 |
| F-02 | P2-03 | S-08 | P0-02, P0-03 |
| F-03 | P2-03 | S-09 | P0-03, P4-08 |
| F-04 | P2-01 | S-10 | P4-07 |
| F-05 | P2-05 | S-11 | P4-07 |
| F-06 | P2-05 | S-12 | P2-01, P1-06 |
| F-07 | P2-06, P1-10 | S-13 | P4-06 |
| F-08 | P1-04 | I-01 | Parked (PK-01) |
| F-09 | P2-08 | I-02 | P2-02 |
| F-10 | P4-04 | I-03 | P2-02 |
| F-11 | P3-02, P3-03, P3-04 | I-04 | P0-03, P4-08 |
| F-12 | P2-09 | I-05 | P2-04 |
| F-13 | P1-10, P4-01 | U-01 | P5-02 |
| U-02 | P5-03 | P-01 | P2-10 |
| U-03 | P1-07 | P-02 | P6-06 |
| U-04 | P1-08 | P-03 | P6-06 |
| U-05 | P1-08 | P-04 | P2-10 |
| U-06 | P1-09 | P-05 | P5-06 |
| U-07 | P1-02 | P-06 | P4-04 |
| U-08 | P1-05 | D-01 | P0-01 |
| U-09 | P1-07 | D-02 | P0-02 |
| U-10 | P5-02 | D-03 | P0-03 |
| U-11 | P1-06 | D-04 | P4-08 |
| U-12 | P0-04 | D-05 | P7-01 |
| U-13 | P1-06 | D-06 | P7-05 |
| U-14 | P1-10 | D-07 | P7-06 |
| | | D-08 | P7-09 |
| | | D-09 | P6-01, P7-04 |
| | | D-10 | P7-04 |
| | | D-11 | P8-01, P8-03 |

---

## Parked — intentionally out of scope for launch

Tracked so they are not rediscovered as "missing":

- [ ] **PK-01** — WebSocket real-time via Reverb (audit I-01); revisit when polling cost matters
- [ ] **PK-02** — Automated data-broker removal engine (audit F-02); after manual volume justifies it
- [ ] **PK-03** — PDF report export; HTML download is acceptable at launch
- [ ] **PK-04** — Multi-user teams / seats
- [ ] **PK-05** — Native mobile apps
- [ ] **PK-06** — Additional locales
- [ ] **PK-07** — Self-hosted scanner cluster

---

## Global definition of done

A task is only complete when **all** of the following hold:

1. Code merged through a branch with at least a self-review diff.
2. Tests added or updated for the behaviour, and the full suite green.
3. No new console errors, no new failed queue jobs, no new error-level log entries in a manual run.
4. The relevant UI state verified at 390 px and 1440 px (where user-facing).
5. Documentation updated if behaviour, configuration or setup changed.
6. The roadmap's specific *Definition of done* for that task is evidenced in the pull request.

**Launch is not done until:** Phases 0–8 are complete, **P8-06** is signed, and the **P8-05** smoke test has passed twice on the production environment.
