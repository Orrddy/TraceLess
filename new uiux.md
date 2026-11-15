# MANDATORY: DOWNLOAD, INSTALL AND USE IMPECCABLE

Before doing ANY UI/UX redesign work, you MUST install the actual Impeccable skill from its official GitHub repository:

https://github.com/pbakaus/impeccable

Do not simply reference Impeccable or imitate its design philosophy. Download and install the real skill.

### INSTALLATION PROCESS

1. Inspect the current development environment and identify the AI coding agent being used.
2. Navigate to the root directory of the TraceLess project.
3. Check whether Impeccable is already installed.
4. If not installed, download and install it using its official installation instructions.

Preferred installation command:

```bash
npx impeccable install
```

Choose project-level installation and enable the design hook if supported.

If the installer cannot be used, clone the official repository and install the appropriate skill files into the correct directory for the current coding agent.

Official repository:
https://github.com/pbakaus/impeccable

5. Read the installed `SKILL.md` completely.
6. Inspect its supporting instructions, commands, references, and available design capabilities.
7. Verify that the installation is valid and that the skill is accessible to the coding agent.
8. Reload or activate the skill if necessary.

### SECURITY

Only download from the official `pbakaus/impeccable` repository.

Inspect installation scripts before executing them. Do not expose secrets, credentials, environment variables, or project data to external services.

### ACTUAL SKILL USAGE

After successful installation:

- Run `/impeccable init` to establish the design context.
- Use `/impeccable audit` to evaluate the existing interface.
- Use `/impeccable craft` to establish the new visual direction.
- Use `/impeccable polish` to refine the implemented interface.
- Use other relevant Impeccable commands when appropriate.

Follow the actual installed skill instructions. Do not invent commands if they differ from the installed version.

### EXECUTION RULE

Installation and verification MUST happen before redesign begins.

Do not pretend the skill was installed if installation failed.

If installation is successful, continue directly into the complete TraceLess redesign described below. Do not stop after installation or merely provide installation instructions.

At the end, report:
- Installation location.
- Whether SKILL.md was verified.
- Which Impeccable commands were actually used.
- What parts of TraceLess were redesigned.

# TRACELESS — COMPLETE UI/UX REDESIGN

/impeccable craft

Act as an award-winning product designer, creative director, senior frontend engineer, and cybersecurity product specialist.

Your task is to completely transform the existing TraceLess SaaS interface into a premium, futuristic, privacy-first digital security platform.

Use the installed **Impeccable skill** throughout the process. Follow its design methodology, inspect the existing application, establish a coherent design system, and implement the redesign in the actual codebase.

Do not simply change colors or round some cards. Create a completely new visual experience.

## 1. PRODUCT IDENTITY

**Brand:** TraceLess

**Product:** Privacy-first digital footprint protection platform.

TraceLess helps individuals understand, manage, and reduce their digital exposure.

Core capabilities:
- Scan publicly available personal information.
- Identify exposed personal information and digital footprints.
- Check for credential leaks and compromised accounts.
- Provide actionable personal information removal guidance.
- Monitor future public exposure.
- Present privacy insights through a secure user dashboard.

The experience should communicate confidentiality, control, intelligence, and trust.

Brand personality: Discreet. Intelligent. Elite.

## 2. VISUAL DIRECTION

Create a high-end cybersecurity intelligence aesthetic.

Think:
- Private intelligence terminal.
- Confidential enterprise security infrastructure.
- Advanced digital privacy operations centre.
- Luxury dark-mode SaaS.
- Futuristic intelligence software.

### Color palette

Build a consistent design token system around:

- Obsidian black: #080A0D
- Deep graphite: #101419
- Elevated surface: #171D24
- Muted silver: #A6B0BC
- Primary text: #F1F4F7
- Electric cyan: #55D6E8
- Restrained emerald: #4ADE9B
- Warning amber: #E9B45D
- Critical red: #EF6262

Use cyan sparingly for active states, scanning indicators, and important interactions.

Avoid excessive neon, rainbow gradients, generic purple gradients, and overused glassmorphism.

### Typography

Use a premium typographic combination:
- Modern editorial sans-serif for headings.
- Highly readable sans-serif for body content.
- Monospaced typography for technical information, identifiers, telemetry, and system status.

Create strong visual hierarchy and generous spacing.

## 3. SIGNATURE VISUAL LANGUAGE

Introduce subtle technical elements that make TraceLess recognizable.

Use tasteful ASCII art, terminal-inspired labels, and confidentiality motifs.

Examples:

       ┌──────────────────────────┐
       │ TRACELESS / PRIVATE OPS  │
       │ STATUS: ENCRYPTED        │
       │ ID: TR-XXXX-XXXX         │
       │ EXPOSURE: MONITORING     │
       └──────────────────────────┘

Additional visual details:
- Small hexadecimal identifiers.
- Encryption-inspired geometric patterns.
- Fine grid structures.
- Scanning line animations.
- Subtle data visualization.
- Secure connection indicators.
- Classified / CONFIDENTIAL micro-labels.
- Minimal technical annotations.
- Beautiful system status indicators.

Use these elements with restraint. The interface must look expensive, not cluttered or like a hacker movie.

Never expose real user credentials or sensitive personal information in decorative elements.

## 4. FULL-SCREEN HOMEPAGE HERO VIDEO

Use this existing video:

C:\My Web Sites\DIGITAL FOOTPRINT\me.mp4

This video must become the centerpiece of the homepage.

Requirements:

- Full viewport-width and full viewport-height hero experience.
- Properly responsive on desktop, tablets, and mobile.
- Video must cover the entire hero without distortion.
- Preserve the video aspect ratio using object-fit: cover.
- Use a sophisticated dark overlay for readable text.
- Place the TraceLess brand and navigation elegantly over the video.
- Create a premium headline, short description, and primary CTA.
- Add subtle technical overlays and confidentiality-inspired design elements.
- Keep the experience immersive and cinematic.

### Audio and playback behavior

The homepage video should attempt to play with sound when a visitor enters the website.

Important:
- Browsers may block autoplay with sound.
- Implement a graceful audio-enabled playback experience when browser policies permit.
- If autoplay with sound is blocked, display a clear, elegant Enable Sound / Enter Experience control.
- The visitor must be able to skip or mute the video.
- Provide an obvious Skip Intro option.
- Once skipped, take the visitor directly into the normal homepage experience.
- Never trap users in the intro.
- Do not replay the intro on every navigation or page refresh unnecessarily.
- Respect browser accessibility and reduced-motion preferences.

Make the intro feel like entering a private digital intelligence environment.

## 5. PROCESSING AND LOADING VIDEO

Use this existing asset:

C:\My Web Sites\DIGITAL FOOTPRINT\bg neon.mp4

This video should serve as a background visual during:
- Digital footprint scans.
- Exposure analysis.
- Credential leak checks.
- Data processing.
- Report generation.
- Other meaningful waiting states.

Requirements:
- Subtle, dark, cinematic presentation.
- Never obstruct important progress information.
- Add refined loading indicators and progress information.
- Display meaningful status messages.
- Ensure the video is muted and loops seamlessly.
- Add a dark overlay to preserve contrast.
- Avoid using the video for tiny loading operations where it would feel excessive.

Examples of status copy:

INITIALIZING SECURE SCAN...
ANALYZING PUBLIC EXPOSURE...
CHECKING CREDENTIAL EXPOSURE...
PROCESSING INTELLIGENCE...
GENERATING PRIVACY REPORT...

The actual displayed progress must reflect real application state. Never fabricate scan progress or claim an operation has completed when it has not.

## 6. HOMEPAGE EXPERIENCE

Design a complete premium landing page.

Suggested structure:

**A. Navigation**
- TraceLess wordmark.
- Product.
- How It Works.
- Privacy.
- Sign In.
- Get Protected CTA.

**B. Hero**
- Full-screen video background.
- Strong headline.
- Clear product explanation.
- Primary CTA: Scan My Digital Footprint.
- Secondary CTA: Explore TraceLess.
- Elegant confidentiality indicators.

Suggested headline:

"Your Digital Footprint.
Under Your Control."

Supporting copy:

"Discover what's exposed. Understand your risks. Take control of your digital identity."

**C. Intelligence Overview**
Showcase the platform's capabilities through visually sophisticated feature sections.

**D. How It Works**
1. Discover your digital footprint.
2. Identify exposed information.
3. Take action with guided removal.
4. Monitor your future exposure.

**E. Security and Confidentiality**
Explain the privacy-first approach, secure handling of user information, and transparent data practices. Only make claims supported by the actual implementation.

**F. Final CTA**
"Your privacy deserves more than hope."

CTA: Take Control of Your Digital Footprint.

## 7. AUTHENTICATED SAAS DASHBOARD

Completely redesign the logged-in product experience.

Create an enterprise-grade privacy intelligence dashboard.

Include:
- Elegant sidebar navigation.
- Compact top navigation.
- Overview dashboard.
- Exposure score visualization.
- Recent scan activity.
- Exposed information summary.
- Credential leak monitoring.
- Removal recommendations.
- Monitoring status.
- Security notifications.
- Account and privacy settings.

Suggested navigation:

OVERVIEW
DIGITAL FOOTPRINT
EXPOSURE REPORT
CREDENTIAL MONITOR
REMOVAL GUIDANCE
CONTINUOUS MONITORING
ACTIVITY LOG
SETTINGS

Design data visualizations that communicate risk clearly.

Use meaningful status labels:
- Protected
- Exposure detected
- Action required
- Monitoring
- Scan completed

Do not invent data or alter existing backend functionality.

## 8. MICRO-INTERACTIONS AND ANIMATIONS

Implement sophisticated, purposeful motion:

- Smooth page transitions.
- Refined hover states.
- Subtle glowing active indicators.
- Elegant button feedback.
- Animated scanning lines.
- Data visualization transitions.
- Smooth sidebar interactions.
- Skeleton loading states.
- Beautiful success and error states.

Avoid excessive animation and unnecessary motion.

## 9. RESPONSIVE DESIGN

The entire platform must be properly designed for:

- Large desktop screens.
- Standard laptops.
- Tablets.
- Mobile phones.

The homepage video must remain immersive on mobile and desktop.

Do not simply shrink the desktop interface. Create proper responsive layouts, mobile navigation, readable typography, appropriate spacing, and usable touch targets.

## 10. IMPORTANT ENGINEERING RULES

This is a redesign of an existing working SaaS.

- Inspect the existing codebase before making changes.
- Understand the existing framework and architecture.
- Preserve existing backend integrations, APIs, authentication, routes, database operations, and business logic.
- Preserve existing features and functionality.
- Do not replace functional components with static mockups.
- Do not fabricate security claims, scan results, or monitoring data.
- Reuse existing components where appropriate, but redesign their presentation.
- Implement reusable design tokens and components.
- Optimize video delivery and loading performance.
- Use appropriate video formats, poster fallbacks, and mobile optimizations.
- Ensure accessible contrast, keyboard navigation, and focus states.
- Respect reduced-motion preferences.
- Avoid unnecessary dependencies.

If the videos are outside the public asset directory, move or copy them into the appropriate project asset directory and update their references. Never leave Windows local filesystem paths hardcoded in production frontend code.

## 11. QUALITY EXPECTATIONS

The finished result must feel like a premium, production-ready cybersecurity SaaS product.

Avoid:
- Generic AI-generated dashboard layouts.
- Excessive rounded cards.
- Unnecessary gradients.
- Purple-and-blue AI aesthetics.
- Overused glassmorphism.
- Excessive glowing borders.
- Random floating elements.
- Decorative ASCII art everywhere.
- Empty sections that only exist to fill space.
- Fake functionality.

Prioritize:
- Exceptional visual hierarchy.
- Strong brand identity.
- Professional typography.
- Intelligent information architecture.
- Precise spacing.
- Premium interactions.
- Clear user journeys.
- Trustworthy privacy communication.

## 12. EXECUTION

Work through the redesign systematically.

1. Inspect the existing project and identify all major application surfaces.
2. Review existing product and design context.
3. Use Impeccable to establish the new visual direction.
4. Redesign the homepage and immersive intro.
5. Redesign the authenticated SaaS dashboard.
6. Redesign remaining application screens and shared components.
7. Integrate both video assets.
8. Implement responsive behavior and interactions.
9. Verify the actual rendered experience on desktop and mobile.
10. Fix visual defects and functional regressions.

Do not stop after creating a design concept or homepage mockup.

Implement the complete redesign in the existing project.

**FINAL DESIGN OBJECTIVE:**

TraceLess should feel like a private, highly sophisticated digital intelligence platform where confidentiality is the default, exposure is measurable, and personal privacy is treated as something worth protecting.