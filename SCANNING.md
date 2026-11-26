# What TraceLess scans

Two different engines run behind the product. Neither uses passwords, private
accounts, dark-web markets or breached credential dumps.

## Snapshot scan (fast)

Checks a monitored username against **23 curated public-profile sites** from the
[WhatsMyName](https://github.com/WebBreacher/WhatsMyName) dataset (CC BY-SA 4.0,
692 source definitions in the current snapshot). It fetches each public profile
page, applies the source's own existence rule and stores the URL, HTTP outcome and
response hash as evidence.

Curated sites: GitHub, GitLab, Reddit, X, Instagram, LinkedIn, Stack Overflow,
Gravatar, Medium, DeviantArt, Spotify, Steam, Pinterest, Disqus, Vimeo, Twitch,
Keybase, Telegram, SoundCloud, Hacker News, Docker Hub, Behance, Dribbble, Flickr.
(LinkedIn is skipped when the installed dataset no longer carries it.)

Cap: up to 5 monitored usernames, so a snapshot is at most ~115 page checks.

## Deep scan (up to two hours)

Deep scan uses the vendored [user-scanner](https://github.com/kaifcodec/user-scanner)
(MIT) and [phonsint](https://github.com/kaifcodec/phonsint) (MIT) engines. These
are **account-registration probes**, not page scrapes: they ask a service whether
an account exists for the identifier. Every deep finding is therefore marked
**unverified** — it proves an account exists, not that personal data is exposed.

| Identifier | Engine | Coverage per identifier |
| --- | --- | --- |
| Username (max 5) | user-scanner, categories `social` + `dev` | **732** site modules (279 social + 453 developer/community) |
| Email (max 1) | user-scanner email modules | **220** email-registration services (social, shopping, dev, dating, streaming, education, finance, …) |
| Phone (max 1) | phonsint | **5** registration probes: Microsoft, Zoho, Amazon, Facebook, Instagram |

Worst-case sweep: 5 × 732 + 220 + 5 = **3,885 public probes**, which is why a deep
scan is budgeted at two hours. It stops early when the crawl finishes; if the
two-hour budget is exhausted the scan fails cleanly with a budget message rather
than publishing partial results. `TRACELESS_DEEP_CATEGORIES` can widen the
username categories (the vendored user-scanner contains 2,545 site modules across
17 categories); the default two categories keep the beta's runtime predictable.

## What is never scanned

- Passwords or password hashes (the password tool is 100% client-side)
- Private, authenticated or paywalled content
- Breach dumps, credential markets or dark-web sources
- Data-broker APIs that require paid access or a breach of their terms

## Attribution

WhatsMyName (CC BY-SA 4.0, Chris Poulter & WebBreacher), user-scanner (MIT, kaifcodec),
phonsint (MIT, kaifcodec), Three.js (MIT), NASA Blue Marble texture (public domain).
## Politeness and concurrency

TraceLess is a guest on every source it checks, so the scanner is deliberately
less aggressive than a normal crawler.

- **Curated snapshot checks:** per-host minimum interval (default 1.2 s), 6
  in-flight requests per worker. Configurable with
  `TRACELESS_SCANNER_DELAY_MS`, `TRACELESS_SCANNER_CONCURRENCY` and
  `TRACELESS_SCANNER_TIMEOUT`.
- **Deep scans:** the vendored user-scanner engines are capped at **8 concurrent
  requests** by default (`TRACELESS_DEEP_CONCURRENCY`, max 32). A deep scan is
  sequential across identifiers, so one identity never opens 732 × 5 requests in
  parallel.
- **Blocked sources:** 401/403/429/451/999 responses are recorded as `unknown`
  with the `source_blocked_request` reason. Existence is never inferred from a
  block; the report says unverified.
- **Source health:** admins can inspect verdict, unknown and blocked rates per
  source at `/admin/sources` (last 7/30/90 days). A rising unknown rate is a
  coverage incident, not evidence that a person has no exposure.

The remaining risk is shared egress: all jobs leave through the application
server's IP. Per-host pacing, a global cap and honest `User-Agent` identify the
crawler; rotate egress only through infrastructure you control and only with the
target's terms in mind.