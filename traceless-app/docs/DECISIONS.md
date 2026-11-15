# Decision log

Durable record of product and architecture decisions for TraceLess. One entry
per decision, newest first. An entry is only closed when the choice, the date,
the owner and the consequence are recorded.

**Status values:** `Open` · `Decided` · `Superseded`

The four `D-*` items below are the decisions that block planned work. See
`TRACELESS_MASTER_TODO.md` for what each one unblocks.

---

## D-01 — Commercial model at launch

- **Status:** Open
- **Owner:** product owner
- **Blocks:** P3-01 (pricing page), P3-02 (payment provider), P8-04 (subscription verification)

**Context.** The application has a paid tier in its data model and UI (`deep_scan`,
$24/month in `config/traceless.php`) but no payment provider. The only way to
obtain the tier today is a demo-only self-grant, which is disabled outside demo
mode. With production configuration, Deep Scan, recurring monitoring and deep
reports are therefore unreachable.

**Options.**

1. **Free-only launch.** One plan, all features, no checkout. Remove the paid tier
   from navigation and copy. Fastest path to launch; monetisation comes later.
2. **Manual invoicing.** Keep the tier, grant it by an admin action after payment
   is arranged off-platform. Requires an admin surface (P2-08 territory) and
   clear copy that checkout is not self-service.
3. **Gateway at launch.** Integrate a provider (e.g. Stripe or Paddle), build
   entitlements, webhooks, receipts and dunning. Adds roughly 2–3 weeks.

**Consequence of each choice.** Option 1 removes P3-02/P3-04 from the critical
path and shrinks launch scope. Option 3 adds the largest remaining workstream
but makes the product a business at launch.

---

## D-02 — Credential monitoring in launch scope

- **Status:** Open
- **Owner:** product owner
- **Blocks:** P2-04, P5-08 (design QA), P8-01 (legal copy)

**Context.** Credential monitoring has a full UI but no data source:
`TRACELESS_BREACH_PROVIDER` is an unused config string, live scans never insert
exposures, and the page currently states that monitored identifiers are "clear".
That statement is untrue and is the highest trust risk in the product.

**Options.**

1. **Integrate a breach-metadata provider** (consent-scoped, evidence retained,
   never passwords). Adds a vendor, a DPA/subprocessor entry, cost per query and
   a new failure mode.
2. **Remove the feature from launch.** Delete it from navigation, the dashboard,
   settings and marketing; keep the tables for later. Honest and cheap.
3. **Ship it as an explicit "not yet available" state.** Lowest effort, but a
   visible dead feature erodes trust more than its absence.

**Consequence.** Option 2 unblocks the fastest honest launch. Option 1 requires
a provider decision before Phase 2 can complete.

---

## D-03 — Hosting, domain and email provider

- **Status:** Open
- **Owner:** product owner
- **Blocks:** P7-01 (host/domain/TLS), P7-03 (transactional email)

**Context.** No production host, domain or sending domain exists. `APP_URL` is
`http://localhost`, which also breaks signed report links, and Resend is
installed but unused locally (`MAIL_MAILER=log`).

**Needed.** Host choice (container host or VPS with the shipped Docker image),
domain, DNS control, and a transactional email provider with SPF/DKIM/DMARC.
Note that `onboarding@resend.dev` cannot deliver to arbitrary recipients, so a
verified domain is required before real sign-ups.

---

## D-04 — Deployment topology for the scanner

- **Status:** Open
- **Owner:** engineering
- **Blocks:** P7-04 (pipeline), P7-09 (queue supervision)

**Context.** `docker-compose.yml` mounts `../python-services` read-only into the
container and runs the same image as `web`, `queue` and `scheduler`. Live scans
shell out to `python3` inside that container.

**Options.**

1. **Single host, one image, three roles** (current design). Simplest to operate;
   the scanner shares CPU with the web tier and a scan spike can affect latency.
2. **Dedicated scanner worker host.** The PHP app calls a job queue that a
   scanner-only host consumes. Better isolation, more infrastructure.

**Consequence.** Option 1 is appropriate until scan volume justifies splitting;
the decision must be recorded because it drives the pipeline and supervision
design.

---

## Decided

*Nothing recorded yet.* Add entries above this line, newest first, using the
same structure, and update the status of the corresponding `D-*` item.
