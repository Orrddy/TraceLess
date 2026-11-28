# Nigeria — lawful public-source probes

This is a research note for a future Nigeria-specific source pack. It separates
things that can be checked from public sources from identity data that is **not**
publicly searchable. It is not a claim that TraceLess currently runs these probes.

## Can be checked from public sources

| Probe | What is public | Practical source | TraceLess treatment |
|---|---|---|---|
| **CAC company / business search** | Registered entity name, RC/BN/IT number, entity type, registration status, some registered particulars | [CAC Public Search](https://search.cac.gov.ng) / [CAC](https://www.cac.gov.ng/) | Good candidate for an **entity** probe. Do not turn it into an unfounded personal-profile probe. |
| **Twitter/X posts** | Posts and profile details the account has made public | X search/profile pages | Candidate for **public social post** checks where the platform and API terms permit. Public posts only; no DMs or private accounts. |
| **Giveaway pages** | Public campaign pages, brand posts, entry rules, listed winners | Official brand sites and social accounts | Candidate for **public campaign checks**. Verify the official domain/account; never submit or collect entrant PII. |
| **Nairaland public profile** | Public member profile at `nairaland.com/{username}`: location, gender, linked X handle, registration date, last seen | Nairaland public profile page | **Added to the deep scan** as a Nigeria-specific public source. Browser-impersonated request; block pages are `unknown`, never matches. |
| **Public professional / social profiles** | Name, handle, bio, public work history | LinkedIn, GitHub, Instagram, Facebook, TikTok, YouTube, Medium | Already the model for the global quick scan. Public profiles only. |
| **Public news and event archives** | Names in articles, speaker lists, public notices | News archives, conference sites | Candidate for public-web search; respect robots/terms and context. |
| **Domain registration (`.ng`)** | Registrant data where the registry publishes it; many records are redacted | [NiRA WHOIS](https://whois.nira.org.ng/) or the relevant registrar | Candidate for domain checks, with redaction-awareness. |
| **Public procurement / treasury data** | Government contracts and published payments, not private personal data | [BPP](https://www.bpp.gov.ng/) · [Open Treasury](https://opentreasury.gov.ng/) | Entity/company-level research, not a person finder. |

## Not public probes — do not scan

| Data | Why it is different |
|---|---|
| **NIN** | NIN data is held in the National Identity Database. NIMC provides NIN verification only through licensed verification clients/approved channels, with consent and purpose limitations. There is no lawful public “NIN lookup”. See [NIMC’s NIN verification client list](https://nimc.gov.ng/docs/NIMC_NVS_Clientsa.pdf) and [NIMC](https://nimc.gov.ng/). |
| **Voter card / PVC** | INEC’s [Voter Verification Service](https://cvr.inecnigeria.org/vvs) is built for a voter to check **their own** registration/PVC status using their details or VIN. It is not an open third-party or bulk lookup. |
| **BVN, bank records, private emails, DMs** | Private financial/communications data. Consent-based verification or lawful authority only. |

## Compliance notes

- Nigeria’s **Data Protection Act 2023** requires lawful basis, purpose limitation,
  data minimisation and security. Read the [NDPA text](https://ndpc.gov.ng/wp-content/uploads/2025/03/NDP-ACT-GAID-2025-MARCH-20TH.pdf) and the
  [NDPC resources](https://ndpc.gov.ng/resources/).
- Public visibility is not the same as unlimited reuse. Follow platform terms,
  `robots.txt`, rate limits and copyright; avoid scraping behind a login or CAPTCHA.
- For NIN/PVC verification, use the official licensed channel, record consent and
  return only the minimum result needed. Those are **verification** products, not
  OSINT probes.
- Do not build a “NIN checker”, “voter card finder” or giveaway entrant scraper.
  Those are the kinds of features that turn a privacy product into a surveillance
  product.

## Recommended Nigeria source pack (public only)

1. CAC entity search — company / business name / RC / BN / IT.
2. Public X/Twitter posts and profile metadata.
3. Public giveaway campaign pages and official brand accounts.
4. Public professional and creative profiles (LinkedIn, GitHub, Instagram, Medium,
   YouTube, TikTok).
5. Public news, event and directory mentions.
6. `.ng` domain registration where published and not redacted.
7. Company-level public procurement / treasury records.

NIN and PVC remain outside the scanner; they require an approved verification
flow and explicit consent, not a public-footprint crawl.
