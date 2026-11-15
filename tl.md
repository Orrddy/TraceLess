
You are a senior Laravel, PHP, Livewire, UI/UX, and cybersecurity product engineer.

Build the TraceLess SaaS platform inside this repository.

TraceLess is a privacy-first digital footprint protection platform that scans publicly available information, identifies exposure, checks credential leaks, provides removal guidance, and monitors future public exposure.

The product must feel like a real, production-quality application—not a static mockup.

DESIGN SOURCE OF TRUTH

First read and follow:

.stitch/DESIGN.md

Use the Stitch-generated screens as the visual reference. Preserve the established design language:

- White and near-white interface backgrounds
- Gothic editorial details
- Cybercore visual language
- Premium iPhone-inspired glassmorphism
- Frosted translucent cards
- Thin black borders
- Electric-blue and cyan accents
- Elegant serif or gothic display accents
- Modern sans-serif body text
- Technical monospace labels
- Subtle grids, scan lines, circuits, and identity nodes
- Premium, spacious, accessible layouts

Do not replace the design with a generic Bootstrap, Tailwind dashboard, or plain white SaaS template.

TECHNOLOGY

Use:

- PHP
- Laravel
- Livewire
- Laravel Blade
- Tailwind CSS
- Alpine.js for lightweight browser interactions
- MySQL or PostgreSQL
- Redis queues
- Laravel broadcasting
- Laravel Reverb or a compatible WebSocket solution
- Laravel notifications
- Laravel scheduler
- PHPUnit and Pest where appropriate
- Docker support
- Python services for scanning integrations and local AI analysis

Use Livewire for interactive dashboard functionality. Use Alpine.js only for small client-side interactions. Do not build the main interface as a separate React or Next.js application.

APPLICATION STRUCTURE

Create or organize the project with:

app/
├── Livewire/
│   ├── Dashboard/
│   ├── Identity/
│   ├── Scans/
│   ├── Findings/
│   ├── Credentials/
│   ├── Removals/
│   ├── Monitoring/
│   └── Reports/
├── Models/
├── Services/
│   ├── ScanOrchestrator.php
│   ├── RiskScoringService.php
│   ├── RemovalWorkflowService.php
│   ├── ReportService.php
│   └── ConsentService.php
├── Jobs/
├── Events/
├── Notifications/
└── Policies/

resources/views/
├── layouts/
├── components/
├── livewire/
├── pages/
└── emails/

database/
├── migrations/
├── factories/
└── seeders/

routes/
├── web.php
├── channels.php
└── console.php

python-services/
├── scanner/
├── normalizer/
├── local-ai/
└── requirements.txt

DESIGN SYSTEM

Use the design system from `.stitch/DESIGN.md`.

Use:
- White background: #FFFFFF
- Soft ivory: #FAFAF8
- Cool white: #F4F7FB
- Pale blue-gray: #EAF0F7
- Black ink: #101217
- Charcoal: #282B33
- Electric blue: #246BFE
- Cyan: #00CFFF
- Digital violet: #805CFF
- Emerald: #16B981
- Amber: #D99000
- Coral: #E44B5F

Use glassmorphism:
- backdrop blur
- translucent white cards
- thin borders
- soft blue-gray shadows
- subtle inner highlights
- 20px to 28px rounded corners

Use gothic and cybercore details carefully:
- Gothic typography only for selected display headings
- Serif editorial accents
- Technical monospace metadata
- Digital grid backgrounds
- Fine circuit traces
- Scan-line effects
- Identity-node graphics
- Faded oversized words such as TRACE, IDENTITY, and EXPOSURE

Keep body text readable and accessible.

CORE PRODUCT FEATURES

Implement:

1. Authentication
- Registration
- Login
- Password reset
- Email verification
- Session management
- Account deletion

2. New identity profile
Allow users to create a profile containing:
- Full name
- Previous names
- Aliases
- Email addresses
- Phone numbers
- Usernames
- Public profile URLs
- City or region

Require users to confirm:
“I own this information or have permission to scan it.”

3. Privacy Snapshot
- Scan one person
- Public information only
- Basic exposure report
- Username discovery
- Public profile discovery
- Basic credential exposure check
- Removal instructions
- Downloadable report

4. Deep Scan
- Multiple people or profiles
- Expanded public source scanning
- Recurring monitoring
- New exposure alerts
- Removal tracking
- Advanced reports
- Priority recommendations

5. Scan results
Display:
- Finding category
- Source
- URL
- Risk level
- Confidence score
- Discovery date
- Last checked date
- Removal method
- Removal status
- Recommended action

6. Credential exposure
- Never request passwords
- Never display passwords
- Check authorized email exposure data
- Show affected service when permitted
- Show approximate exposure date
- Recommend password changes
- Recommend multi-factor authentication
- Allow users to mark actions as completed

7. Removal action center
Statuses:
- Not started
- Ready to remove
- In progress
- Waiting for source
- User action required
- Removed
- Could not verify
- Reappeared

Provide:
- Automatic removal workflows where supported
- Manual step-by-step instructions where needed
- Expected response times
- Source links
- Completion tracking
- Notifications

8. Monitoring
Deep Scan users can enable recurring scans for:
- New profiles
- New public contact information
- Data broker listings
- Reappearing information
- Credential exposure
- Possible impersonation

DASHBOARD

Build a Livewire dashboard with:

- Privacy score
- Exposure ratio
- Public findings count
- High-risk findings
- Credential exposure status
- Removal progress
- Monitoring status
- Recent findings
- Recommended actions
- Latest scan activity

Privacy score:
Use a 0–100 estimate based on:
- Number of findings
- Sensitivity
- Source count
- Credential exposure
- Removal progress
- Monitoring status

Display:
“This score is an estimate designed to help prioritize privacy actions.”

REAL-TIME FUNCTIONALITY

The application must use real-time behavior, not only static placeholder animations.

Implement real-time updates for:

- Scan progress
- Sources checked
- Findings discovered
- Privacy score recalculation
- Removal status changes
- Monitoring alerts
- Notifications
- Report generation
- Background job status

Use:

- Laravel queued jobs
- Redis
- Events
- Broadcasting
- Laravel Reverb or compatible WebSockets
- Livewire listeners
- Laravel Echo where necessary

Example scan states:

```text
queued
initializing
checking_sources
normalizing_results
calculating_risk
generating_report
completed
failed
cancelled
```

When a scan is running, show:
- Animated progress ring
- Current scan stage
- Sources checked
- Findings found
- Live activity feed
- Estimated progress
- Cancel scan button

If external scanning integrations are not available yet, build a realistic local development adapter with seeded demo data. Clearly separate demo mode from production mode.

ANIMATIONS

Add tasteful cybercore animations:

- Scanning line moving through glass cards
- Animated privacy score ring
- Pulsing identity nodes
- Data points appearing in real time
- Progress bar transitions
- Glass cards entering with soft fade and slide
- Hover glow around interactive components
- Alert pulse for new findings
- Subtle circuit-line movement
- Animated chart updates
- Smooth modal and drawer transitions

Respect `prefers-reduced-motion`.

Do not use:
- Excessive flashing
- Distracting background animations
- Rapid strobing
- Animation that reduces readability
- Fake security claims

LANDING PAGE

Build a polished landing page with:

Hero headline:
“Your digital footprint leaves a shadow.”

Supporting text:
“TraceLess finds your exposed public information, identifies credential risks, and helps you reduce your online footprint with clear, actionable guidance.”

Eyebrow:
“PERSONAL PRIVACY INTELLIGENCE // 2027 READINESS”

Buttons:
- Start Your Privacy Scan
- Explore the Exposure Map

Include:
- Dashboard preview
- Exposure ratio preview
- Password leak preview
- Removal progress preview
- Gothic cybercore visual details
- 2027 preparedness section

Use responsible wording:
“Prepare for evolving digital threats in 2027.”

Do not claim that a confirmed hacker outbreak will occur in 2027.

DASHBOARD ROUTES

Create routes for:

- `/dashboard`
- `/identity`
- `/identity/create`
- `/scans`
- `/scans/create`
- `/scans/{scan}`
- `/findings`
- `/findings/{finding}`
- `/credentials`
- `/removals`
- `/removals/{removal}`
- `/monitoring`
- `/reports`
- `/settings`

DATABASE MODELS

Create migrations and models for:

- User
- IdentityProfile
- PublicIdentifier
- Scan
- ScanSource
- Finding
- CredentialExposure
- RemovalTask
- RemovalInstruction
- MonitoringSubscription
- Alert
- Report
- ConsentRecord
- AuditLog
- Subscription
- BillingRecord

Include:
- Relationships
- Status enums
- Soft deletes where appropriate
- Timestamps
- Consent tracking
- Audit history
- User ownership policies

PYTHON SCANNING SERVICES

Create a clean integration boundary for Python workers.

The Python services may integrate with:
- WhatsMyName data
- user-scanner
- Approved public-data sources
- Credential exposure services
- Local AI analysis

Do not expose Python command-line tools directly to users.

Laravel should:
1. Create a scan
2. Validate consent
3. Queue a scan job
4. Call the Python service securely
5. Receive normalized results
6. Store findings
7. Calculate risk
8. Broadcast progress
9. Generate a report
10. Notify the user

Use a normalized result structure:

```json
{
  "source": "example.com",
  "category": "social_profile",
  "identifier_type": "username",
  "identifier": "example_user",
  "url": "https://example.com/example_user",
  "confidence": 0.91,
  "risk": "moderate",
  "removal_method": "manual_instructions",
  "status": "needs_user_action"
}
```

LOCAL AI SAFETY

Local AI may:
- Categorize findings
- Detect duplicates
- Identify false positives
- Summarize exposure
- Prioritize tasks
- Explain risks
- Generate removal instructions from approved templates

Local AI must not:
- Bypass authentication
- Evade rate limits
- Circumvent anti-bot controls
- Access private data
- Expose passwords
- Facilitate stalking, harassment, doxxing, or unauthorized profiling

Implement:
- Consent checks
- Source restrictions
- Rate limits
- Audit logging
- Abuse prevention
- Human review for high-risk findings
- Data retention controls

SECURITY REQUIREMENTS

Implement:
- CSRF protection
- Authorization policies
- Input validation
- Rate limiting
- Encrypted sensitive fields
- Secure job payloads
- Signed URLs for reports
- Audit logs
- Secure secrets management
- No password storage from scan targets
- User data deletion
- Consent records
- Error logging without exposing personal data

TESTING

Create tests for:
- Authentication
- Consent validation
- Identity profile creation
- Scan creation
- Scan authorization
- Queue dispatch
- Scan progress updates
- Finding normalization
- Risk scoring
- Removal statuses
- Credential exposure display
- Report generation
- User data deletion
- Unauthorized access prevention

DOCUMENTATION

Update the repository documentation with:

- Installation instructions
- Environment variables
- Database setup
- Queue setup
- WebSocket setup
- Python service setup
- Local AI setup
- Demo mode instructions
- Testing instructions
- Deployment instructions
- Security model
- Data retention policy
- Architecture diagram
- Real-time event documentation

IMPLEMENTATION PROCESS

Work in small, verifiable steps:

1. Inspect the existing repository
2. Read `.stitch/DESIGN.md`
3. Identify current files and framework status
4. Create a technical implementation plan
5. Set up or verify Laravel
6. Set up the database
7. Implement authentication
8. Implement the design system
9. Build the landing page
10. Build identity setup
11. Build scan creation
12. Build dashboard
13. Build scan progress
14. Add real-time events
15. Build scan results
16. Build credential exposure
17. Build removal workflows
18. Add monitoring
19. Add reports
20. Add testing
21. Update documentation
22. Run the application and verify every major flow

Do not overwrite existing user work without checking it first.

Do not claim a feature is complete unless it works end-to-end or is clearly marked as demo mode.

At the end of each implementation step, report:
- Files changed
- Features completed
- Tests run
- Any remaining limitations
- The next recommended step
