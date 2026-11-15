@extends('layouts.marketing')

@section('title', 'TraceLess // Private Operations Command Center')

@section('content')
<div x-data="{
    soundEnabled: false,
    introSeen: localStorage.getItem('traceless_intro_seen') === 'true',
    reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
    initHero() {
        const video = this.$refs.heroVideo;
        if (!video) return;
        if (this.reducedMotion || this.introSeen) {
            video.pause();
            return;
        }
        // Try sound-on first, then fall back to a muted loop.
        video.muted = false;
        video.play().catch(() => {
            video.muted = true;
            this.soundEnabled = false;
            video.play().catch(() => {});
        });
    },
    toggleSound() {
        const video = this.$refs.heroVideo;
        if (!video) return;
        this.soundEnabled = !this.soundEnabled;
        video.muted = !this.soundEnabled;
        video.play().catch(() => {});
    },
    skipIntro() {
        localStorage.setItem('traceless_intro_seen', 'true');
        this.introSeen = true;
        const video = this.$refs.heroVideo;
        if (video) video.pause();
        document.getElementById('overview-section')?.scrollIntoView({ behavior: 'smooth' });
    }
}" x-init="initHero()" class="relative">

    {{-- FULL-VIEWPORT BRIEFING --}}
    <section class="video-hero-container flex flex-col justify-between" id="hero-viewport">
        <video x-ref="heroVideo" autoplay loop playsinline muted preload="auto" aria-hidden="true">
            <source src="{{ asset('assets/videos/hero-intro.mp4') }}" type="video/mp4">
        </video>

        <div class="video-hero-overlay"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.14] terminal-bg" style="background-color: transparent;"></div>

        {{-- Quiet status row --}}
        <div class="relative z-10 w-full px-5 pt-4 sm:px-10">
            <div class="flex items-center justify-between gap-4 border-b border-line pb-3 text-xs text-body">
                <div class="flex items-center gap-3">
                    <span class="rounded-full border border-line bg-surface px-2.5 py-1 text-ink">Public data only</span>
                    <span class="hidden sm:inline">No passwords, ever</span>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="toggleSound()"
                            class="flex items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 py-1 text-body transition-colors hover:text-ink"
                            :aria-pressed="soundEnabled">
                        <span class="material-symbols-outlined text-[14px]" x-text="soundEnabled ? 'volume_up' : 'volume_off'"></span>
                        <span x-text="soundEnabled ? 'Sound on' : 'Enable sound'"></span>
                    </button>
                    <button type="button" @click="skipIntro()"
                            class="rounded-lg border border-line bg-surface px-2.5 py-1 text-body transition-colors hover:text-ink">
                        Skip intro ↓
                    </button>
                </div>
            </div>
        </div>

        {{-- Hero copy --}}
        <div class="relative z-10 mx-auto my-auto w-full max-w-[1440px] px-5 py-10 sm:px-10">
            <div class="max-w-3xl space-y-6">
                <div class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium text-body backdrop-blur-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                    Public sources only · Evidence kept for every finding
                </div>

                <h1 class="font-display text-[2.6rem] font-semibold leading-[1.02] tracking-[-0.035em] text-ink sm:text-6xl lg:text-[4.5rem]">
                    Your digital footprint.<br>
                    <span class="text-accent">Under your control.</span>
                </h1>

                <p class="max-w-xl font-body text-base leading-relaxed text-body sm:text-lg">
                    See which public profiles, open registries and archived posts connect to you. Every match is verified against the source, and every exposure comes with a clear path to removal. No passwords, ever.
                </p>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <a href="{{ route('register') }}" class="btn-ops-primary px-6 py-3.5 text-sm">
                        <span class="material-symbols-outlined text-[18px]">radar</span>
                        <span>Scan my digital footprint</span>
                    </a>
                    <a href="#overview-section" class="btn-ops-secondary px-6 py-3.5 text-sm">
                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                        <span>See how verification works</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 gap-3 border-t border-line pt-5 font-mono text-[10px] text-body/85 sm:grid-cols-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[15px] text-success">public</span>
                        <span>Public web data only</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[15px] text-success">key_off</span>
                        <span>Zero passwords requested</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[15px] text-success">verified_user</span>
                        <span>Consent recorded per identity</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative z-10 w-full pb-6 text-center">
            <a href="#overview-section" class="inline-flex flex-col items-center gap-1 text-xs text-body transition-colors hover:text-ink">
                <span>Continue briefing</span>
                <span class="material-symbols-outlined animate-bounce text-[16px]">keyboard_arrow_down</span>
            </a>
        </div>
    </section>

    {{-- INTELLIGENCE OVERVIEW --}}
    <section id="overview-section" class="mx-auto max-w-[1440px] px-4 py-24 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12">
            <div class="space-y-6 lg:col-span-5">
                <div class="ops-badge ops-badge-cyan">Evidence matrix</div>
                <h2 class="font-display text-3xl font-semibold tracking-[-0.03em] text-ink sm:text-4xl">
                    Privacy intelligence built on proof, not alerts.
                </h2>
                <p class="font-body text-sm leading-relaxed text-body">
                    Most checkers leave you with a warning and no next step. TraceLess records how each match was found, states plainly when a source could not be verified, and turns what remains into removal work you can track.
                </p>

                <div class="space-y-3 font-mono text-xs">
                    <div class="flex items-center justify-between rounded-lg border border-line bg-surface p-3.5">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[18px] text-accent">account_tree</span>
                            <span class="text-ink">Federated handle discovery</span>
                        </div>
                        <span class="text-accent">WhatsMyName</span>
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-line bg-surface p-3.5">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[18px] text-warning">fact_check</span>
                            <span class="text-ink">Verification evidence per finding</span>
                        </div>
                        <span class="text-warning">Status + hash</span>
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-line bg-surface p-3.5">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[18px] text-faint">vpn_key_off</span>
                            <span class="text-body">Breach metadata provider</span>
                        </div>
                        <span class="text-faint">Not configured</span>
                    </div>
                </div>
            </div>

            <div id="capabilities" class="lg:col-span-7">
                <div id="intelligence" class="ops-card scan-line-overlay p-6 sm:p-8">
                    <div class="mb-6 flex items-center justify-between border-b border-line pb-4 font-mono text-xs">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-success"></span>
                            <span class="font-semibold text-ink">Illustrative evidence record</span>
                        </div>
                        <span class="text-warning">DEMO DATA</span>
                    </div>

                    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border border-line bg-canvas p-4 text-center">
                            <div class="mb-1 font-mono text-3xl font-bold text-ink">73<span class="text-xs text-body">/100</span></div>
                            <div class="font-mono text-[9px] uppercase tracking-wider text-body">Posture index</div>
                        </div>
                        <div class="rounded-lg border border-line bg-canvas p-4 text-center">
                            <div class="mb-1 font-mono text-3xl font-bold text-warning">14</div>
                            <div class="font-mono text-[9px] uppercase tracking-wider text-body">Verified nodes</div>
                        </div>
                        <div class="rounded-lg border border-line bg-canvas p-4 text-center">
                            <div class="mb-1 font-mono text-3xl font-bold text-success">2</div>
                            <div class="font-mono text-[9px] uppercase tracking-wider text-body">Removals ready</div>
                        </div>
                    </div>

                    <div class="space-y-1.5 rounded-lg border border-line bg-canvas p-4 font-mono text-[11px] leading-relaxed text-body/90">
                        <div class="text-accent">[INFO] Consent recorded. Starting public-source check for target handle...</div>
                        <div>[200] Public profile catalog queried... <span class="text-success">MATCH (GitHub, Reddit, DeviantArt)</span></div>
                        <div>[200] Match rule satisfied, response hash stored... <span class="text-success">VERIFIED</span></div>
                        <div>[403] Source blocked automated checks... <span class="text-warning">UNKNOWN, NOT A FINDING</span></div>
                        <div class="text-success">[OK] Actionable removal tasks compiled. Evidence attached to each record.</div>
                    </div>

                    <div class="mt-6 flex flex-col gap-2 font-mono text-[10px] text-faint sm:flex-row sm:items-center sm:justify-between">
                        <span>ILLUSTRATIVE ONLY · NO PRIVATE DATA USED</span>
                        <a href="{{ route('register') }}" class="flex items-center gap-1 text-accent hover:underline">
                            Run it on your own profile →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section id="how-it-works" class="border-t border-line bg-surface py-20">
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
            <div class="mx-auto mb-14 max-w-2xl space-y-3 text-center">
                <div class="ops-badge">How it works</div>
                <h2 class="font-display text-3xl font-semibold tracking-[-0.03em] text-ink sm:text-4xl">Four steps to lower exposure</h2>
                <p class="font-body text-sm leading-relaxed text-body">Every scan is deliberate, legally consented, and strictly limited to public-source discovery.</p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <div class="ops-card space-y-4 p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-accent">Step 1</div>
                    <h3 class="font-display text-base font-semibold text-ink">Monitored profile</h3>
                    <p class="font-body text-xs leading-relaxed text-body">Record your legal name, known handles and public emails behind an explicit ownership consent gate.</p>
                </div>

                <div class="ops-card space-y-4 p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-accent">Step 2</div>
                    <h3 class="font-display text-base font-semibold text-ink">Public discovery</h3>
                    <p class="font-body text-xs leading-relaxed text-body">Check a curated public-source catalog for profiles and mentions that connect to your identifiers.</p>
                </div>

                <div class="ops-card space-y-4 p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-accent">Step 3</div>
                    <h3 class="font-display text-base font-semibold text-ink">Evidence review</h3>
                    <p class="font-body text-xs leading-relaxed text-body">Each result carries its HTTP outcome, match rule and response hash. Blocked sources read <span class="text-warning">unknown</span>, never invented.</p>
                </div>

                <div class="ops-card space-y-4 p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-accent">Step 4</div>
                    <h3 class="font-display text-base font-semibold text-ink">Tracked removal</h3>
                    <p class="font-body text-xs leading-relaxed text-body">Follow source-specific guidance, record what you sent, and monitor whether the record returns.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- WHY PUBLIC EXPOSURE MATTERS --}}
    <section id="preparedness" class="mx-auto max-w-[1440px] border-t border-line px-4 py-24 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-6">
                <div class="ops-badge">Why it matters</div>
                <h2 class="font-display text-3xl font-semibold leading-tight tracking-[-0.03em] text-ink sm:text-4xl">
                    Public fragments are training data.
                </h2>
                <p class="font-body text-sm leading-relaxed text-body">
                    Automated profiling, voice cloning and targeted phishing rarely need to break into a private server. They assemble what is already public: old usernames, archived forum posts, directory listings and forgotten accounts.
                </p>
                <div class="space-y-2 rounded-lg border border-line bg-surface p-4 font-mono text-xs text-body">
                    <div class="flex items-center gap-2 font-semibold text-ink">
                        <span class="material-symbols-outlined text-[18px] text-accent">verified_user</span>
                        <span>Minimisation, not disappearance</span>
                    </div>
                    <p class="text-[11px] leading-relaxed text-faint">
                        Reducing your public surface lowers the material available to automated social engineering. It cannot guarantee complete deletion by third parties.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-6">
                <div class="ops-card space-y-2 p-5">
                    <span class="material-symbols-outlined text-accent">hub</span>
                    <h4 class="font-display text-sm font-semibold text-ink">Synthetic profiling</h4>
                    <p class="font-body text-[11px] leading-relaxed text-body">Remove directory listings that feed identity profiles and automated enrichment.</p>
                </div>
                <div class="ops-card space-y-2 p-5">
                    <span class="material-symbols-outlined text-success">history</span>
                    <h4 class="font-display text-sm font-semibold text-ink">Forgotten accounts</h4>
                    <p class="font-body text-[11px] leading-relaxed text-body">Find stale profiles created years ago on platforms you no longer use.</p>
                </div>
                <div class="ops-card space-y-2 p-5">
                    <span class="material-symbols-outlined text-warning">visibility_off</span>
                    <h4 class="font-display text-sm font-semibold text-ink">Old public posts</h4>
                    <p class="font-body text-[11px] leading-relaxed text-body">Surface archived text that still links your name to an old handle.</p>
                </div>
                <div class="ops-card space-y-2 p-5">
                    <span class="material-symbols-outlined text-danger">notifications_active</span>
                    <h4 class="font-display text-sm font-semibold text-ink">Re-exposure watch</h4>
                    <p class="font-body text-[11px] leading-relaxed text-body">Be told when a record you removed is published again at the same source.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="mx-auto max-w-[1440px] border-t border-line px-4 py-24 sm:px-6 lg:px-8">
        <div class="ops-card scan-line-overlay mx-auto max-w-3xl space-y-6 p-10 text-center sm:p-12">
            <div class="text-xs font-semibold uppercase tracking-wide text-accent">Take back control</div>
            <h2 class="font-display text-3xl font-semibold tracking-[-0.03em] text-ink sm:text-4xl">Your privacy deserves more than hope.</h2>
            <p class="mx-auto max-w-lg font-body text-sm leading-relaxed text-body">
                Start with one authorised identity. See what is actually public, understand how it was verified, and decide what to remove first.
            </p>
            <div class="flex flex-wrap justify-center gap-4 pt-2">
                <a href="{{ route('register') }}" class="btn-ops-primary px-8 py-3.5 text-sm">
                    <span>Start an authorised scan</span>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </div>
            <div class="font-mono text-[10px] text-faint">
                NO PASSWORDS REQUESTED // PUBLIC SOURCES ONLY // DEMO MODE LABELLED
            </div>
        </div>
    </section>
</div>
@endsection
