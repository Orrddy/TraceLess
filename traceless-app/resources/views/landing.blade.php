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

        {{-- Slim telemetry row (deliberately quieter than the nav) --}}
        <div class="relative z-10 w-full px-5 pt-4 sm:px-10">
            <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-3 font-mono text-[9px] uppercase tracking-[0.16em] text-[#A6B0BC]/70">
                <div class="flex items-center gap-3">
                    <span class="rounded border border-white/10 bg-black/50 px-2 py-1 text-white">Confidential briefing</span>
                    <span class="hidden sm:inline">Public sources only</span>
                    <span class="hidden md:inline text-[#5D6B7A]">Demo environment</span>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="toggleSound()"
                            class="flex items-center gap-1.5 rounded border border-white/15 bg-black/60 px-2.5 py-1 text-white transition-colors hover:border-[#55D6E8]"
                            :aria-pressed="soundEnabled">
                        <span class="material-symbols-outlined text-[14px]" x-text="soundEnabled ? 'volume_up' : 'volume_off'"></span>
                        <span x-text="soundEnabled ? 'Sound on' : 'Enable sound'"></span>
                    </button>
                    <button type="button" @click="skipIntro()"
                            class="rounded border border-transparent bg-white/10 px-2.5 py-1 text-white transition-colors hover:bg-white/20">
                        Skip intro ↓
                    </button>
                </div>
            </div>
        </div>

        {{-- Briefing copy --}}
        <div class="relative z-10 mx-auto my-auto w-full max-w-[1440px] px-5 py-10 sm:px-10">
            <div class="max-w-3xl space-y-6">
                <div class="inline-flex flex-col gap-0.5 border border-[#55D6E8]/25 bg-[#080A0D]/70 px-3.5 py-2 font-mono text-[9px] uppercase leading-relaxed tracking-[0.18em] backdrop-blur-sm">
                    <span class="text-[#55D6E8]">TraceLess // private intelligence</span>
                    <span class="text-[#5D6B7A]">Classification: confidential · Scope: public web</span>
                </div>

                <h1 class="font-display text-[2.6rem] font-semibold leading-[1.02] tracking-[-0.035em] text-white sm:text-6xl lg:text-[4.5rem]">
                    Your digital footprint.<br>
                    <span class="text-[#55D6E8]">Under your control.</span>
                </h1>

                <p class="max-w-xl font-body text-base leading-relaxed text-[#A6B0BC] sm:text-lg">
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

                <div class="grid grid-cols-1 gap-3 border-t border-white/10 pt-5 font-mono text-[10px] text-[#A6B0BC]/85 sm:grid-cols-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[15px] text-[#4ADE9B]">public</span>
                        <span>Public web data only</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[15px] text-[#4ADE9B]">key_off</span>
                        <span>Zero passwords requested</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[15px] text-[#4ADE9B]">verified_user</span>
                        <span>Consent recorded per identity</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="relative z-10 w-full pb-6 text-center">
            <a href="#overview-section" class="inline-flex flex-col items-center gap-1 font-mono text-[9px] uppercase tracking-[0.2em] text-[#A6B0BC]/60 transition-colors hover:text-white">
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
                <h2 class="font-display text-3xl font-semibold tracking-[-0.03em] text-white sm:text-4xl">
                    Privacy intelligence built on proof, not alerts.
                </h2>
                <p class="font-body text-sm leading-relaxed text-[#A6B0BC]">
                    Most checkers leave you with a warning and no next step. TraceLess records how each match was found, states plainly when a source could not be verified, and turns what remains into removal work you can track.
                </p>

                <div class="space-y-3 font-mono text-xs">
                    <div class="flex items-center justify-between rounded-lg border border-white/5 bg-[#101419] p-3.5">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[18px] text-[#55D6E8]">account_tree</span>
                            <span class="text-white">Federated handle discovery</span>
                        </div>
                        <span class="text-[#55D6E8]">WhatsMyName</span>
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-white/5 bg-[#101419] p-3.5">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[18px] text-[#E9B45D]">fact_check</span>
                            <span class="text-white">Verification evidence per finding</span>
                        </div>
                        <span class="text-[#E9B45D]">Status + hash</span>
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-white/5 bg-[#101419] p-3.5">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[18px] text-[#5D6B7A]">vpn_key_off</span>
                            <span class="text-[#A6B0BC]">Breach metadata provider</span>
                        </div>
                        <span class="text-[#5D6B7A]">Not configured</span>
                    </div>
                </div>
            </div>

            <div id="capabilities" class="lg:col-span-7">
                <div id="intelligence" class="ops-card scan-line-overlay p-6 sm:p-8">
                    <div class="mb-6 flex items-center justify-between border-b border-white/10 pb-4 font-mono text-xs">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#4ADE9B]"></span>
                            <span class="font-semibold text-white">Illustrative evidence record</span>
                        </div>
                        <span class="text-[#E9B45D]">DEMO DATA</span>
                    </div>

                    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border border-white/5 bg-[#080A0D] p-4 text-center">
                            <div class="mb-1 font-mono text-3xl font-bold text-white">73<span class="text-xs text-[#A6B0BC]">/100</span></div>
                            <div class="font-mono text-[9px] uppercase tracking-wider text-[#A6B0BC]">Posture index</div>
                        </div>
                        <div class="rounded-lg border border-white/5 bg-[#080A0D] p-4 text-center">
                            <div class="mb-1 font-mono text-3xl font-bold text-[#E9B45D]">14</div>
                            <div class="font-mono text-[9px] uppercase tracking-wider text-[#A6B0BC]">Verified nodes</div>
                        </div>
                        <div class="rounded-lg border border-white/5 bg-[#080A0D] p-4 text-center">
                            <div class="mb-1 font-mono text-3xl font-bold text-[#4ADE9B]">2</div>
                            <div class="font-mono text-[9px] uppercase tracking-wider text-[#A6B0BC]">Removals ready</div>
                        </div>
                    </div>

                    <div class="space-y-1.5 rounded-lg border border-white/5 bg-[#080A0D] p-4 font-mono text-[11px] leading-relaxed text-[#A6B0BC]/90">
                        <div class="text-[#55D6E8]">[INFO] Consent recorded. Starting public-source check for target handle...</div>
                        <div>[200] Public profile catalog queried... <span class="text-[#4ADE9B]">MATCH (GitHub, Reddit, DeviantArt)</span></div>
                        <div>[200] Match rule satisfied, response hash stored... <span class="text-[#4ADE9B]">VERIFIED</span></div>
                        <div>[403] Source blocked automated checks... <span class="text-[#E9B45D]">UNKNOWN, NOT A FINDING</span></div>
                        <div class="text-[#4ADE9B]">[OK] Actionable removal tasks compiled. Evidence attached to each record.</div>
                    </div>

                    <div class="mt-6 flex flex-col gap-2 font-mono text-[10px] text-[#5D6B7A] sm:flex-row sm:items-center sm:justify-between">
                        <span>ILLUSTRATIVE ONLY · NO PRIVATE DATA USED</span>
                        <a href="{{ route('register') }}" class="flex items-center gap-1 text-[#55D6E8] hover:underline">
                            Run it on your own profile →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- METHODOLOGY --}}
    <section id="methodology" class="border-t border-white/[0.06] bg-[#0B0E12] py-20">
        <div class="mx-auto max-w-[1440px] px-4 sm:px-6 lg:px-8">
            <div class="mx-auto mb-14 max-w-2xl space-y-3 text-center">
                <div class="ops-badge ops-badge-faint">Standard operating procedure</div>
                <h2 class="font-display text-3xl font-semibold tracking-[-0.03em] text-white sm:text-4xl">Four steps to lower exposure</h2>
                <p class="font-body text-sm leading-relaxed text-[#A6B0BC]">Every scan is deliberate, legally consented, and strictly limited to public-source discovery.</p>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <div class="ops-card space-y-4 p-6">
                    <div class="font-mono text-xs text-[#55D6E8]">STAGE 01 // IDENTITY</div>
                    <h3 class="font-display text-base font-semibold text-white">Monitored profile</h3>
                    <p class="font-body text-xs leading-relaxed text-[#A6B0BC]">Record your legal name, known handles and public emails behind an explicit ownership consent gate.</p>
                </div>

                <div class="ops-card space-y-4 p-6">
                    <div class="font-mono text-xs text-[#55D6E8]">STAGE 02 // RECON</div>
                    <h3 class="font-display text-base font-semibold text-white">Public discovery</h3>
                    <p class="font-body text-xs leading-relaxed text-[#A6B0BC]">Check a curated public-source catalog for profiles and mentions that connect to your identifiers.</p>
                </div>

                <div class="ops-card space-y-4 p-6">
                    <div class="font-mono text-xs text-[#55D6E8]">STAGE 03 // VERIFY</div>
                    <h3 class="font-display text-base font-semibold text-white">Evidence review</h3>
                    <p class="font-body text-xs leading-relaxed text-[#A6B0BC]">Each result carries its HTTP outcome, match rule and response hash. Blocked sources read <span class="text-[#E9B45D]">unknown</span>, never invented.</p>
                </div>

                <div class="ops-card space-y-4 p-6">
                    <div class="font-mono text-xs text-[#55D6E8]">STAGE 04 // REMEDIATE</div>
                    <h3 class="font-display text-base font-semibold text-white">Tracked removal</h3>
                    <p class="font-body text-xs leading-relaxed text-[#A6B0BC]">Follow source-specific guidance, record what you sent, and monitor whether the record returns.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- WHY PUBLIC EXPOSURE MATTERS --}}
    <section id="preparedness" class="mx-auto max-w-[1440px] border-t border-white/[0.06] px-4 py-24 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12">
            <div class="space-y-4 lg:col-span-6">
                <div class="ops-badge ops-badge-coral">Intelligence briefing</div>
                <h2 class="font-display text-3xl font-semibold leading-tight tracking-[-0.03em] text-white sm:text-4xl">
                    Public fragments are training data.
                </h2>
                <p class="font-body text-sm leading-relaxed text-[#A6B0BC]">
                    Automated profiling, voice cloning and targeted phishing rarely need to break into a private server. They assemble what is already public: old usernames, archived forum posts, directory listings and forgotten accounts.
                </p>
                <div class="space-y-2 rounded-lg border border-white/5 bg-[#101419] p-4 font-mono text-xs text-[#A6B0BC]">
                    <div class="flex items-center gap-2 font-semibold text-white">
                        <span class="material-symbols-outlined text-[18px] text-[#55D6E8]">verified_user</span>
                        <span>Minimisation, not disappearance</span>
                    </div>
                    <p class="text-[11px] leading-relaxed text-[#5D6B7A]">
                        Reducing your public surface lowers the material available to automated social engineering. It cannot guarantee complete deletion by third parties.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:col-span-6">
                <div class="ops-card space-y-2 p-5">
                    <span class="material-symbols-outlined text-[#55D6E8]">hub</span>
                    <h4 class="font-display text-sm font-semibold text-white">Synthetic profiling</h4>
                    <p class="font-body text-[11px] leading-relaxed text-[#A6B0BC]">Remove directory listings that feed identity dossiers and automated enrichment.</p>
                </div>
                <div class="ops-card space-y-2 p-5">
                    <span class="material-symbols-outlined text-[#4ADE9B]">history</span>
                    <h4 class="font-display text-sm font-semibold text-white">Forgotten accounts</h4>
                    <p class="font-body text-[11px] leading-relaxed text-[#A6B0BC]">Find stale profiles created years ago on platforms you no longer use.</p>
                </div>
                <div class="ops-card space-y-2 p-5">
                    <span class="material-symbols-outlined text-[#E9B45D]">visibility_off</span>
                    <h4 class="font-display text-sm font-semibold text-white">Old public posts</h4>
                    <p class="font-body text-[11px] leading-relaxed text-[#A6B0BC]">Surface archived text that still links your name to an old handle.</p>
                </div>
                <div class="ops-card space-y-2 p-5">
                    <span class="material-symbols-outlined text-[#EF6262]">notifications_active</span>
                    <h4 class="font-display text-sm font-semibold text-white">Re-exposure watch</h4>
                    <p class="font-body text-[11px] leading-relaxed text-[#A6B0BC]">Be told when a record you removed is published again at the same source.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="mx-auto max-w-[1440px] border-t border-white/[0.06] px-4 py-24 sm:px-6 lg:px-8">
        <div class="ops-card scan-line-overlay mx-auto max-w-3xl space-y-6 p-10 text-center sm:p-12">
            <div class="font-mono text-xs uppercase tracking-[0.2em] text-[#55D6E8]">Take back confidentiality</div>
            <h2 class="font-display text-3xl font-semibold tracking-[-0.03em] text-white sm:text-4xl">Your privacy deserves more than hope.</h2>
            <p class="mx-auto max-w-lg font-body text-sm leading-relaxed text-[#A6B0BC]">
                Start with one authorised identity. See what is actually public, understand how it was verified, and decide what to remove first.
            </p>
            <div class="flex flex-wrap justify-center gap-4 pt-2">
                <a href="{{ route('register') }}" class="btn-ops-primary px-8 py-3.5 text-sm">
                    <span>Start an authorised scan</span>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </div>
            <div class="font-mono text-[10px] text-[#5D6B7A]">
                NO PASSWORDS REQUESTED // PUBLIC SOURCES ONLY // DEMO MODE LABELLED
            </div>
        </div>
    </section>
</div>
@endsection
