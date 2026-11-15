# TraceLess Design System

## Product

TraceLess is a premium privacy and digital-footprint protection platform. It helps users discover public exposure, identify credential leaks, follow removal instructions, and monitor future risks.

## Creative Direction

Design TraceLess as an elegant, premium privacy command center inspired by modern iPhone hardware and Apple-style interface materials:

- Soft, luminous, glass-like surfaces
- Layered translucent cards
- Subtle background blur
- Thin borders with low-opacity white highlights
- Calm security visuals rather than aggressive hacker imagery
- Spacious layouts with strong hierarchy
- Smooth, restrained motion
- Premium editorial typography
- Dark atmospheric backgrounds instead of plain white backgrounds

The interface should feel like a secure glass dashboard floating above a deep, ambient background.

## Platform

- Primary: responsive web application
- Secondary: mobile-first layouts inspired by iOS
- Desktop: 1440px canvas, responsive down to 1024px
- Mobile: 390px iPhone-style viewport
- Use safe-area spacing and large touch targets on mobile

## Color System

### Backgrounds

- `Midnight Canvas`: #07111F — main page background
- `Deep Navy`: #0B1728 — secondary background
- `Blue Black`: #101C2E — elevated background
- `Atmospheric Blue`: #12304A — soft background glow
- `Ambient Violet`: #27204A — occasional decorative glow

Do not use plain white page backgrounds. Do not use large empty white sections.

### Glass Surfaces

- `Glass Primary`: rgba(255, 255, 255, 0.10)
- `Glass Secondary`: rgba(255, 255, 255, 0.06)
- `Glass Bright`: rgba(255, 255, 255, 0.16)
- `Glass Border`: rgba(255, 255, 255, 0.18)
- `Glass Highlight`: rgba(255, 255, 255, 0.28)
- `Glass Shadow`: rgba(0, 0, 0, 0.30)

Use translucent surfaces over dark gradients. Every glass card should retain readable contrast.

### Text

- `Primary Text`: #F8FAFC
- `Secondary Text`: #CBD5E1
- `Muted Text`: #94A3B8
- `Disabled Text`: #64748B

### Accent Colors

- `Electric Blue`: #4F8CFF — primary actions and links
- `Cyan Glow`: #35D7FF — active states and scan progress
- `Emerald`: #25D39B — safe, complete, removed
- `Amber`: #F5B84B — warning and attention
- `Coral`: #FF6B6B — high-risk or critical exposure
- `Violet`: #9B8CFF — premium AI and monitoring features

Use red and coral sparingly. TraceLess should feel calm, not alarming.

## Glassmorphism Rules

Use an iPhone-inspired glass material system:

- `backdrop-filter: blur(24px)` or equivalent
- Background opacity between 6% and 16%
- 1px translucent borders
- Very soft shadows
- Subtle inner highlights along top edges
- Slightly brighter hover states
- Avoid excessive blur that reduces readability

Recommended card treatment:

```text
background: rgba(255, 255, 255, 0.08)
border: 1px solid rgba(255, 255, 255, 0.16)
backdrop-filter: blur(24px)
box-shadow: 0 20px 60px rgba(0, 0, 0, 0.24)
border-radius: 24px
```

Use glass layers for:

- Navigation bars
- Dashboard cards
- Scan progress panels
- Privacy score cards
- Finding details
- Modal dialogs
- Mobile bottom navigation
- Notification panels

## Typography

Use a modern neo-grotesk or humanist sans-serif similar to SF Pro Display, Inter, or Geist.

- Display heading: 56px / 1.05 / 700
- Desktop page heading: 40px / 1.1 / 700
- Mobile page heading: 30px / 1.1 / 700
- Section heading: 24px / 1.2 / 650
- Card heading: 17px / 1.3 / 650
- Body: 15px / 1.55 / 400
- Small label: 12px / 1.4 / 600
- Metric number: 32px / 1 / 700

Use sentence case. Avoid excessive uppercase text. Use generous line spacing for explanations.

## Layout

- Desktop maximum content width: 1280px
- Mobile horizontal padding: 20px
- Desktop horizontal padding: 40px
- Standard card gap: 16px
- Section gap: 56px to 96px
- Use a 12-column desktop grid
- Use a 4-column mobile grid where useful
- Keep primary actions visible without overwhelming the page

## Radius and Elevation

- Small controls: 10px
- Inputs: 14px
- Standard cards: 20px
- Featured cards: 28px
- Modal sheets: 32px
- Pills: 999px

Use soft depth, not harsh borders or heavy drop shadows.

## Brand Mark

Create a simple TraceLess mark based on a fading path, reduced fingerprint, or disappearing signal line. It should work as:

- Full logo with wordmark
- Compact icon
- Mobile app icon
- Favicon

Avoid literal locks, hacker masks, skulls, or threatening imagery.

## Navigation

Desktop dashboard navigation:

- Overview
- My Identity
- Scan Results
- Credential Exposure
- Removal Actions
- Monitoring
- Reports
- Settings

Use a translucent vertical sidebar with the active item highlighted by an electric-blue glass pill.

Mobile navigation:

- Home
- Scan
- Findings
- Actions
- Alerts
- Profile

Use a floating translucent bottom navigation bar with a strong active state.

## Core Components

### Primary Button

Electric-blue gradient, white label, 12–14px radius, subtle glow on hover. Use verbs such as:

- Start Your Scan
- View Findings
- Begin Removal
- Enable Monitoring

### Secondary Button

Transparent glass surface, thin translucent border, white text.

### Privacy Score

Use a circular or semi-circular progress visualization. Combine the score with plain-language context. Never present it as an absolute security guarantee.

### Risk Badge

- Low: blue glass badge
- Moderate: amber glass badge
- High: coral glass badge
- Critical: red-coral glass badge

Always include text, not color alone.

### Finding Card

Show category icon, finding title, source, risk badge, discovery date, confidence, removal status, and next action.

### Scan Progress

Use a luminous circular progress ring with a soft cyan glow. Show source categories being checked and a calm explanation that only public information is scanned.

### Charts

Use thin lines, soft gradients, translucent fills, and clear labels. Avoid dense dashboards and unnecessary chart decoration.

## Screen Atmosphere

Use dark navy backgrounds with subtle radial glows behind important content:

- Blue glow behind primary scan actions
- Violet glow behind local AI or monitoring features
- Cyan glow around active scan progress
- Emerald glow for completed removal actions

Keep backgrounds visually rich but not distracting.

## Imagery and Icons

Prefer:

- Abstract network lines
- Soft topographic patterns
- Fading identity trails
- Layered translucent orbs
- Minimal line icons
- Abstract privacy diagrams

Avoid:

- Stock photos of hackers
- Hooded figures
- Red warning walls
- Overly literal cybersecurity graphics
- Excessive padlock imagery

## Content Tone

Use language that is:

- Reassuring
- Direct
- Transparent
- Empowering
- Non-technical where possible

Preferred phrases:

- “Find your exposure.”
- “Take back your privacy.”
- “Public information only.”
- “No passwords required.”
- “Remove what can be removed.”
- “Follow clear instructions for everything else.”
- “Your privacy score is an estimate designed to help prioritize actions.”

Avoid:

- “You are under attack.”
- “Guaranteed deletion.”
- “We erase you from the internet.”
- Unverified claims about a specific hacker outbreak in 2027.

For 2027 messaging, use:

“Prepare for evolving digital threats in 2027.”

Explain that TraceLess helps reduce exposure to impersonation, automated scams, leaked credentials, and misuse of public information.

## Privacy and Safety Requirements

Every relevant flow should make clear:

- TraceLess scans publicly available information only
- Users must scan themselves or have permission
- TraceLess never requests or displays passwords
- Removal is not guaranteed for every source
- Manual instructions are provided when automation is unavailable
- Local AI is used for privacy-preserving analysis only
- Local AI must not bypass authentication, evade rate limits, access private data, or enable harassment or unauthorized profiling

## Accessibility

- Maintain WCAG-friendly contrast
- Never rely on color alone
- Include visible focus states
- Use keyboard-accessible controls
- Use descriptive labels
- Keep body text readable on glass surfaces
- Provide reduced-motion support
- Use large touch targets on mobile

## Stitch Generation Rules

Generate all screens as one coherent product system. Preserve the same colors, glass materials, spacing, typography, icon style, navigation, button treatment, privacy language, and risk-status patterns across every screen.

The final experience should feel like a premium iPhone-inspired privacy command center floating in a dark, atmospheric digital environment.
