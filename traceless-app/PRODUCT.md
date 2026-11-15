# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Privacy-conscious individuals who want to know what personal information about them is publicly discoverable, and who need a practical path to reduce it. They arrive after a specific worry (an old username, a data-broker listing, a leaked email) and want evidence and next steps without handing over passwords. Secondary audience: security-minded professionals evaluating a privacy tool for themselves or a small team.

## Product Purpose

TraceLess maps a person's public digital footprint, scores the exposure risk, and turns each record into a removal action that can be tracked to completion. It exists so people can act on their own public exposure with verified evidence rather than vague alerts. Success means a user can see what is exposed, understand why it matters, start the right removal, and know when the record is gone or has reappeared.

## Positioning

Passwordless, consent-gated public-source reconnaissance with stored verification evidence. Every finding is checked against the source's own existence rule and carries its HTTP outcome, match rule, and response hash. TraceLess never requests or stores target passwords, never invents breach data when no provider is configured, and presents removal as guided work rather than a guaranteed deletion service.

## Operating Context

- Web SaaS built with Laravel 12, Livewire 3, Tailwind, Alpine.js.
- Passwordless authentication: six-digit email codes, no account passwords.
- Scans run in one of two clearly separated modes:
  - demo mode: deterministic local catalog, labelled as demo;
  - live mode: a Python worker queries a curated public-source list from the WhatsMyName dataset and reports `verified`, `not_found`, `unknown`, or `skipped`.
- Breach metadata is skipped until a provider is configured; the UI states this explicitly.
- Users configure an identity profile with an explicit ownership/permission consent before any scan.
- Findings, scans, removals, reports and monitoring are owner-scoped.
- Reports use signed, expiring download links.

## Capabilities and Constraints

- Public data only. Usernames are the supported live-scan identifier; no credentials or private data are collected.
- Removal tracking supports automated-request and manual-instruction workflows. Removal is never guaranteed.
- Monitoring tracks reappearance of previously removed records and new findings.
- Reports summarise posture, findings and removal status.
- Constraint: the product must visually distinguish demo mode from live mode and must never present unimplemented providers as working.
- Constraint: assessment scores are prioritisation aids, not factual privacy guarantees.
- Undecided: production breach-metadata provider, broader source coverage, human review workflow.

## Brand Commitments

- Name: TraceLess.
- Voice: discreet, precise, calm authority. Short declarative sentences. No hype, no fear-mongering, no hacker-movie clutter.
- Personality: discreet, intelligent, elite.
- Pinned visual direction from the product brief: obsidian/graphite surfaces, electric cyan used sparingly, restrained emerald/amber/red status colors, editorial sans display, readable body sans, monospace restricted to technical data and telemetry.
- Pinned assets: `public/assets/videos/hero-intro.mp4` (homepage hero, sourced from the owner's `me.mp4`) and `public/assets/videos/scanner-loop.mp4` (processing backdrop, sourced from `bg neon.mp4`). Both are real, owner-provided assets.
- Signature motifs: confidentiality labels, fine grids, scanning lines, small identifiers, status telemetry — used with restraint.

## Evidence on Hand

- Working application surfaces: landing page, authentication, identity setup, scans, findings, credentials, removals, monitoring, reports, settings.
- Real verification evidence stored per finding (match rule, HTTP status, response hash, timestamp).
- Real dataset attribution: WhatsMyName (CC BY-SA 4.0), user-scanner (MIT).
- Demo seed account for local review only.
- Absent and must not be fabricated: customer logos, testimonials, benchmarks, commercial claims, breach-provider coverage, removal success rates.

## Product Principles

1. Evidence over alarm: show how a record was verified, and say `unknown` when it was not.
2. No passwords, ever: authentication and scans must never ask for credential material.
3. Consent is a gate, not a checkbox: no scan without recorded ownership authorization.
4. Every exposure ends in an action: a finding without a removal path is incomplete.
5. Tell the truth about mode: demo data and unconfigured providers are always labelled.

## Accessibility & Inclusion

Keyboard-operable controls, visible focus states, accessible contrast, and respect for `prefers-reduced-motion`. Video is muted and skippable; sound is opt-in.
