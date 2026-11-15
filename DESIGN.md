# TraceLess design direction

The file antislop reads for direction. `antislop.md` is the filter; this is the soul.
It records what the product is, what it should feel like, and the dials every screen
holds. When a decision is not covered here, the answer is "what makes the evidence
easier to trust", not "what looks modern".

**Dial: ENERGY 1 / RHYTHM 2 / MOTION 1**

---

## What the product is

TraceLess tells a person what is publicly known about them, shows the evidence behind
every claim, and gives them a path to remove it. The differentiator is not detection,
it is **proof**: each finding carries the rule that matched, the HTTP status, the URL
checked, and a hash of the response.

That is the design brief. This is not a spy product and not a hacker terminal. It is a
**record of public facts, presented calmly enough to act on**. The closest reference
points are a well-made research report, a bank statement, and a legal disclosure, not
a monitoring dashboard from a movie.

Audience: a worried individual, often not technical, who arrived after finding
something about themselves. They are anxious. The interface should lower the
temperature, not raise it.

## What it must never look like

- A hacker terminal: ASCII, monospace headings, "RECON", "ENCLAVE", scan lines, grids.
- A cyber-security dark theme with cyan glow and telemetry strips.
- A generic SaaS template: gradient hero, three feature cards, pill eyebrow, fake logos.
- An alarm: red everywhere, pulsing dots, threat language.

## Identity motif: the evidence line

Every verified finding carries a **2px accent rule on its left edge** and a **monospace
evidence value** (status code, rule name, or the first characters of the response hash).
Nothing else in the product uses a left rule, and nothing else uses monospace.

- The rule appears **only when there is evidence**: a verified record. An unverified or
  unknown record has no rule, because the absence is information.
- This is a state marker, not decoration. If a row has no evidence, it gets no line.
- The score is presented as an **estimate** with that word visible, never as a gauge of safety.

Reason, written down as R-31 requires: the product's only real claim is proof, so the
proof gets the one repeated visual gesture. A left edge is read before text is read.

## Palette

Neutral paper plus ink plus one accent. Status colours are semantic only.

| Role | Light | Dark | Reason |
|---|---|---|---|
| Canvas | #FAFAF9 | #0B0F14 | Warm near-white, not clinical #FFF: a document, not a screen |
| Surface | #F4F4F2 | #111720 | Grouping without borders |
| Elevated | #FFFFFF | #161D26 | Cards and modals sit above the page |
| Line | #E2E6EC | #26303C | Hairline, 1px, used to separate, never to decorate |
| Ink | #0C1116 | #ECF1F6 | Primary text, 17:1 |
| Body | #475569 | #AEBAC7 | Paragraphs, 7.6:1 |
| Muted | #526071 | #94A3B8 | Secondary labels, 6.3:1 |
| Faint | #64748B | #8494A6 | Tertiary and hints, 4.8:1. AA floor, never lighter |
| Accent | #1D4ED8 | #7AA2FF | One accent. Institutional blue: trust and links, not "AI blue" |
| Success | #047857 | #34D399 | Removed, verified, safe |
| Warning | #B45309 | #F5B85B | Needs attention, unverified |
| Danger | #B91C1C | #F87171 | Critical exposure only, never for decoration |

Rules:

1. **One accent.** Links, primary buttons, focus rings, the evidence line. Nowhere else.
2. **Status colour appears only with a status word.** No colour-only meaning.
3. **No gradients, no glow, no coloured shadows.** A surface is flat or it has one
   soft neutral shadow.
4. **Faint is the AA floor.** If a label needs to be lighter than #64748B, it should
   not be text.

## Typography

| Use | Face | Reason |
|---|---|---|
| Display, page titles | Schibsted Grotesk | A newsroom face: editorial authority, not the default Inter/Geist |
| UI, body, labels | Inter | Chosen for legibility at 13-15px in dense tables, not for style |
| Evidence values only | JetBrains Mono | Status codes, rules and hashes are data; monospace signals "verbatim" |

Rules:

- No monospace headings. No uppercase labels with wide letter-spacing. Labels are
  sentence case, 12-13px, medium weight.
- One `h1` per page, sentence case, at most 40px. Headings state the finding or the
  action, never a mood.
- Numbers in tables use tabular figures.

## Layout

- One focal point per screen: the score on the dashboard, the finding on its detail
  page, the scan action on the create page. Everything else defers.
- The app shell is grouped: "Monitoring" (overview, footprint, exposure, credentials,
  removal) and "Account" (reports, settings). The active item is marked by weight and
  a tinted background, not colour alone.
- Content max-width 1152px. Cards are 12px radius; controls 8px; pills only for status
  badges. Nothing else is pill-shaped.
- Section rhythm varies (RHYTHM 2): a page does not repeat the same card grid twice in
  a row. Density is a tool: the dashboard is dense, the landing page is spacious.

## Motion

MOTION 1. Hover, focus, and state transitions only, 120-180ms, ease-out.

- No entrance animations, no parallax, no pulsing dots, no endless loops.
- The one moving thing in the product is the scan progress indicator, because it
  reflects real work. It stops when the work stops.
- `prefers-reduced-motion` disables all of it.

## Copy

Voice: **plain, specific, unexcited**. A person explaining something important.

- Say what happened and what to do: "12 public profiles found. 4 need your action."
- Never: "Execute Recon Scan", "enclave", "intelligence nodes", "dossier", "deploy".
- Never invent numbers, testimonials, or security claims. If a number has no source,
  it does not appear.
- Say "estimate" when it is an estimate, and "unknown" when it is unknown. The product's
  honesty is the brand.
- No em dashes. Commas, colons, full stops, parentheses.
- Buttons use verbs the user performs: "Run a scan", "Start removal", "Download report".

## Landing page

One job: explain the product in one screen and get the visitor to start a scan.

- Hero keeps the owner's video, under a calm scrim, with one headline, one sentence,
  one primary action, and the honest limits stated once ("public sources only", "no
  passwords", "removal is not guaranteed").
- No eyebrow pill, no decorative status dot, no fake dashboard screenshot.
- Sections follow the real story: what gets checked, how verification works, what
  removal involves, what the product cannot do. No testimonials, no logo bar.

## Accessibility (non-negotiable)

- WCAG AA everywhere, measured, not assumed: 4.5:1 body text, 3:1 large text.
- Every control reaches by keyboard, visible focus ring, `cursor: pointer`.
- Status is never colour alone: every badge carries a word.
- Tap targets 44px on mobile. No horizontal scroll at 390px.
- Every media element has dimensions and a reserved box.

## What the next pass must do

1. Apply this palette and type scale to every screen (the tokens exist; the copy and
   mono leftovers do not).
2. Put the evidence line on verified findings only.
3. Replace the remaining costume vocabulary with plain language.
4. Vary section rhythm on the landing page so it stops reading as a template.
