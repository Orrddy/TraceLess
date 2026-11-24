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
