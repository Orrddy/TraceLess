# TraceLess design direction

## Dark futuristic / cinematic tech

TraceLess is a privacy and digital-footprint product that must feel capable,
premium, and calm. The interface should communicate a serious modern tool, not
an alarm, a hacker terminal, or an untrustworthy surveillance product.

This document is the visual source of truth for the November 2026 redesign.
It replaces the previous light, report-like direction.

## Preservation rule

This is a presentation-layer design system. Existing features, routes, APIs,
data structures, scan logic, consent controls, reports, integrations, and
access policies stay intact.

The full-screen hero video at
`traceless-app/public/assets/videos/hero-intro.mp4` is part of the visual
identity. Keep its source, full-screen placement, playback behavior, and role
as the primary hero background. Use overlays and foreground composition to
improve it; never replace it with a new video, static image, or 3D scene.

## Visual personality

Think: cinematic creative-technology studio meets an evidence-led privacy
product.

- Deep-black, near-black, and charcoal foundations
- Oversized modern sans-serif typography
- Fine technical details, not fake terminal content
- Electric blue and cyan used as focused evidence/action accents
- Dark translucent panels, refined borders, and soft depth
- Icon-led navigation with labels retained where they aid comprehension
- Generous space and asymmetry on marketing surfaces
- High signal, low visual noise in dashboards

Avoid generic neon, large rainbow gradients, dense dashboard cards, hooded
hacker imagery, fake threat counters, or alert-red decoration.

## Palette

| Role | Value | Usage |
| --- | --- | --- |
| Canvas | `#030509` | Page and deep backdrop |
| Surface | `#090C14` | Grouped regions |
| Elevated | `#0F141F` | Cards, drawers, controls |
| Line | `#2F3A4D` | Hairline separation |
| Ink | `#F6F8FC` | Main text |
| Body | `#B4BECF` | Explanations |
| Muted | `#94A0B5` | Secondary metadata |
| Accent | `#6CB1FF` | Primary actions and links |
| Cyan | `#52E0FF` | Evidence, active scans, focus |
| Violet | `#9F7AEA` | Secondary depth only |
| Success | `#4ADE80` | Completed/verified status |
| Warning | `#FBBF24` | Attention or unknown state |
| Danger | `#FB7185` | Critical exposure only |

Status colours always appear with a written status. Use colour sparingly;
information and proof are more important than decoration.

## Typography

- **Display and page titles:** Space Grotesk, bold, tight tracking, oversized
  where the marketing narrative needs impact.
- **UI and body:** Inter, chosen for dense product interfaces and accessibility.
- **Evidence and machine-readable metadata:** JetBrains Mono only.

Hero headings may use 72–108px desktop scale. Product headings should be
readable rather than theatrical. Never use monospace for ordinary prose or
large headings.

## Surfaces and depth

Use dark glass materials deliberately:

- 18–20px corner radius for primary cards and panels
- Low-opacity dark fills with a 1px low-contrast border
- Subtle top-edge highlight on elevated objects
- Deep neutral shadows, never bright coloured card shadows
- Backdrop blur only where it supports visual layering

Cards must remain legible in front of the hero video and over the ambient
background. Do not cover the video with a giant opaque panel.

## Hero composition

- Video is the dominant visual layer.
- Use cinematic black gradient overlays for content readability.
- Add a lightweight, pointer-responsive particle/evidence layer above the
  video and below the content. It must not interact with the video itself.
- Build the heading on a clear left-aligned editorial axis.
- Use one compact live-signal panel on large screens only.
- Keep the required trust facts prominent: public data only, no passwords,
  and consent per identity.

## Navigation

- Use a glassy dark top bar for marketing pages and a dark, elevated sidebar
  for the app.
- Navigation items use an outline icon before the label.
- Preserve labels in the product sidebar. Icon-only controls must have an
  accessible name and native tooltip/title.
- Active routes receive a fine cyan evidence edge and a restrained blue fill.
- Hover motion is short and subtle: a 1–2px shift, border response, and text
  contrast change.

## Motion

Motion should establish polish, context, and state—not perform for its own sake.

- 180–320ms transitions for buttons, cards, navigation, drawers, and modals
- Soft depth/opacity reveals when meaningful content enters the viewport
- Slow scan-line and particle movement only in decorative or active-scan areas
- No strobing, flashing, looping alert pulses, or aggressive parallax
- Honour `prefers-reduced-motion`; remove decorative canvas animation entirely
  and reduce all other transitions

## Responsive performance

- Preserve video playback behavior, but use its existing mobile fallback rules
  and crop the video deliberately on small screens.
- Decorative interaction must be lightweight and disabled below tablet width
  and for reduced-motion preferences.
- Do not add duplicate rendering frameworks or heavyweight 3D libraries unless
  a measurable product need exists.
- On mobile, prioritize readable evidence, large targets, and scan/removal
  actions over decoration.

## Accessibility

- Maintain WCAG-friendly contrast for every text and status combination.
- Never communicate risk or completion through colour alone.
- Use visible keyboard focus states.
- Keep all icon-only actions labelled for assistive technology.
- Use readable body sizes and preserve user zoom.

## Content tone

Use evidence-first, calm language:

- “Public data only”
- “No passwords requested”
- “Verified against the source”
- “Removal is not guaranteed”
- “Scores help prioritise action”

Do not promise complete removal from the internet or claim a specific future
hacker outbreak. Explain that public exposure can support phishing,
impersonation, and automated scams without using fear as the primary design
device.
