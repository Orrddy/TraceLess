# Sender avatar (BIMI) — showing the TraceLess logo next to the sender name

In Gmail, Apple Mail and Outlook the picture shown beside a sender is **not**
something the email HTML controls. The standard for a brand-controlled avatar is
**BIMI** (Brand Indicators for Message Identification).

## What the app already does

- Every OTP email now has the TraceLess icon in its header (remote image:
  `https://traceless.orrddy.store/apple-touch-icon.png`). This is what recipients
  see immediately, regardless of BIMI support.
- A BIMI-ready square logo is hosted at
  `https://traceless.orrddy.store/brand/bimi-logo.svg`
  (SVG Tiny PS, 512×512, no external references).
- Mail is authenticated: Resend DKIM (`resend._domainkey.orrddy.store`) is verified
  and aligned with the From domain.

## What is missing for the inbox avatar

1. **DMARC enforcement.** The domain currently publishes
   `v=DMARC1; p=none; rua=mailto:traceless@orrddy.store`.
   BIMI requires `p=quarantine` or `p=reject` with `pct=100`.
   Change the `_dmarc.orrddy.store` TXT record to:

   ```
   v=DMARC1; p=quarantine; pct=100; rua=mailto:traceless@orrddy.store; adkim=r; aspf=r
   ```

   Confirm one real message from Resend passes both SPF and DKIM before tightening
   (Gmail → Show original). Resend uses its own return-path subdomains
   (`send.orrddy.store`), so DKIM alignment is what matters.

2. **BIMI DNS record.** Add a TXT record:

   | Host | Type | Value |
   | --- | --- | --- |
   | `default._bimi` | TXT | `v=BIMI1; l=https://traceless.orrddy.store/brand/bimi-logo.svg;` |

3. **A Verified Mark Certificate (VMC) — required by Gmail and Apple Mail.**
   Without a certificate, Gmail and Apple Mail will keep showing the generic
   avatar; some smaller providers may show the logo with just the free BIMI record.
   A VMC is issued by DigiCert, Entrust and a few resellers, costs roughly
   **$900–$1,700 per year**, and requires a **registered trademark** for the logo.
   Once issued, host the PEM publicly and append it to the record:

   ```
   v=BIMI1; l=https://traceless.orrddy.store/brand/bimi-logo.svg; a=https://traceless.orrddy.store/brand/vmc.pem
   ```

4. **Wait for propagation**, then send a test to a Gmail account and inspect
   “Show original” / the sender chip. BIMI status can also be checked in Gmail’s
   message details once published.

## Realistic beta recommendation

- Keep the in-email header logo (already live in the template) — it gives the
  brand impression on every client today.
- Stay on `p=none` → `p=quarantine` only when you can monitor DMARC reports.
- Skip the VMC during the free beta; revisit when there is a registered trademark
  and the budget for it.
- A free Gravatar for `do-not-reply@orrddy.store` may show in some desktop clients
  but **not** in Gmail’s inbox list, so it is not a substitute.

## Updating the logo

- Email header image: replace `public/apple-touch-icon.png` (square PNG, ideally
  256×256 or 512×512) or set an absolute `MAIL_LOGO_URL` in Altare variables.
- BIMI logo: replace `public/brand/bimi-logo.svg` with another SVG Tiny PS file,
  redeploy, and update the BIMI record if the filename changes.
