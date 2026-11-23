@extends('layouts.marketing')

@section('title', 'TraceLess · Know what is public about you')

@section('content')
<div class="mx-auto max-w-[1200px] px-5 sm:px-8">

    {{-- HERO --}}
    <section class="grid items-center gap-12 py-14 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:py-20">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="v-pill"><span class="v-dot v-dot-green"></span>v1.0 · Public beta</span>
                <span class="v-pill">Free during beta</span>
            </div>

            <h1 class="mt-6 max-w-xl text-[clamp(2.5rem,5vw,4rem)] font-semibold leading-[1.02] tracking-[-0.045em]">
                <span class="bg-gradient-to-b from-[#171717] to-[#555] bg-clip-text text-transparent dark:from-white dark:to-[#9aa3b2]">Know what the internet already knows about you.</span>
            </h1>

            <p class="mt-5 max-w-lg text-[15px] leading-relaxed text-body">
                TraceLess finds the profiles, directories and public records that connect to your identity,
                proves each match against its source, and gives you a removal path. No passwords, ever.
            </p>

            <div class="mt-7 flex flex-wrap items-center gap-2">
                <a href="{{ route('register') }}" class="v-btn v-btn-primary">
                    Scan my footprint
                    <span aria-hidden="true">→</span>
                </a>
                <a href="#how-it-works" class="v-btn v-btn-secondary">How it works</a>
            </div>

            <ul class="mt-7 flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px] text-muted">
                <li class="flex items-center gap-2"><span class="v-dot v-dot-green"></span>Consent recorded per identity</li>
                <li class="flex items-center gap-2"><span class="v-dot v-dot-green"></span>Public sources only</li>
                <li class="flex items-center gap-2"><span class="v-dot v-dot-green"></span>No password to steal</li>
            </ul>
        </div>

        {{-- LIVE NETWORK INTELLIGENCE --}}
        <div class="min-w-0"
             x-data="{
                state: 'loading',
                data: null,
                note: '',
                polls: 0,
                timer: null,
                init() { this.load(); },
                async load() {
                    try {
                        const response = await fetch('/api/ip-intelligence', { headers: { accept: 'application/json' }, cache: 'no-store' });
                        if (response.status === 422) {
                            this.state = 'unavailable';
                            this.note = 'This check needs a public internet connection, so it is paused on your network.';
                            return;
                        }
                        if (response.status === 429) {
                            this.state = 'unavailable';
                            this.note = 'Too many requests from your network. Try again in a minute.';
                            return;
                        }
                        if (!response.ok) {
                            this.state = 'unavailable';
                            this.note = 'Network intelligence is temporarily unavailable.';
                            return;
                        }
                        const payload = await response.json();
                        if (payload.status === 'ready' && payload.data) {
                            this.data = payload.data;
                            this.state = 'ready';
                            return;
                        }
                        this.state = 'warming';
                        this.schedule();
                    } catch (error) {
                        this.state = 'unavailable';
                        this.note = 'Network intelligence is temporarily unavailable.';
                    }
                },
                schedule() {
                    clearTimeout(this.timer);
                    if (this.polls >= 8) { this.state = 'slow'; return; }
                    this.polls += 1;
                    this.timer = setTimeout(() => this.load(), 2000 + Math.random() * 800);
                },
                providerCount() {
                    if (!this.data || !this.data.provider_status) return 0;
                    return Object.values(this.data.provider_status).filter(v => v === 'ok').length;
                },
                providerTotal() {
                    if (!this.data || !this.data.provider_status) return 0;
                    return Object.keys(this.data.provider_status).length;
                },
                gradeTone(grade) {
                    if (grade === 'A' || grade === 'B') return 'v-dot-green';
                    if (grade === 'C') return 'v-dot-amber';
                    return 'v-dot-red';
                }
             }"
             x-init="init()"
             class="v-card overflow-hidden"
             aria-label="Your live network intelligence">
            <div class="flex items-center justify-between border-b border-line/60 px-5 py-3.5">
                <div class="flex items-center gap-2">
                    <span class="v-dot" x-bind:class="state === 'ready' ? 'v-dot-green' : (state === 'warming' ? 'v-dot-amber' : 'v-dot-gray')"></span>
                    <span class="text-[13px] font-medium">Your network, seen from here</span>
                </div>
                <span class="v-kicker" x-text="state === 'ready' ? 'Live · cached 48h' : (state === 'warming' ? 'Reading signals…' : 'Unavailable')"></span>
            </div>

            {{-- Warming skeleton --}}
            <div x-show="state === 'warming' || state === 'loading'" x-cloak class="px-5 py-6">
                <div class="animate-pulse space-y-3">
                    <div class="h-2.5 w-1/3 rounded bg-[#ebebeb] dark:bg-[#1c2333]"></div>
                    <div class="h-8 w-2/3 rounded bg-[#ebebeb] dark:bg-[#1c2333]"></div>
                    <div class="grid grid-cols-2 gap-3 pt-2">
                        <div class="h-10 rounded bg-[#f2f2f2] dark:bg-[#141926]"></div>
                        <div class="h-10 rounded bg-[#f2f2f2] dark:bg-[#141926]"></div>
                        <div class="h-10 rounded bg-[#f2f2f2] dark:bg-[#141926]"></div>
                        <div class="h-10 rounded bg-[#f2f2f2] dark:bg-[#141926]"></div>
                    </div>
                </div>
                <p class="mt-4 text-[12px] leading-relaxed text-muted">
                    Four public intelligence sources are being consulted in parallel. This usually takes two to five seconds and never delays the page.
                </p>
            </div>

            {{-- Slow --}}
            <div x-show="state === 'slow'" x-cloak class="px-5 py-6">
                <p class="text-[13px] leading-relaxed text-body">
                    The public sources are taking longer than usual. The page stays usable — the result will be cached for 48 hours when it lands.
                </p>
                <div class="mt-4 flex gap-2">
                    <button type="button" class="v-btn v-btn-secondary" x-on:click="polls = 0; state = 'loading'; load();">Check again</button>
                    <a href="{{ route('ip.check') }}" class="v-btn v-btn-ghost">Open the IP tool</a>
                </div>
            </div>

            {{-- Unavailable --}}
            <div x-show="state === 'unavailable'" x-cloak class="px-5 py-6">
                <p class="text-[13px] leading-relaxed text-body" x-text="note"></p>
                <p class="mt-2 text-[12px] leading-relaxed text-muted">
                    TraceLess shows nothing rather than guessing when a source cannot be reached.
                </p>
                <a href="{{ route('ip.check') }}" class="v-link mt-3 inline-block">Check any IP manually →</a>
            </div>

            {{-- Ready --}}
            <div x-show="state === 'ready'" x-cloak class="px-5 py-5">
                <div class="flex items-end justify-between gap-4">
                    <div class="min-w-0">
                        <p class="v-kicker">Public IP address</p>
                        <p class="mt-1 truncate font-[family-name:'Geist_Mono',ui-monospace,monospace] text-xl font-medium tracking-tight" x-text="data?.ip ?? '—'"></p>
                        <p class="mt-1 text-[12px] text-body">
                            <span x-text="[data?.city, data?.region, data?.country].filter(Boolean).join(', ') || 'Location unavailable'"></span>
                            <span class="text-muted">·</span>
                            <span x-text="data?.timezone || '—'"></span>
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="v-kicker">IP risk</p>
                        <div class="mt-1 flex items-center justify-end gap-2">
                            <span class="v-dot" x-bind:class="gradeTone(data?.risk_grade)"></span>
                            <span class="text-2xl font-semibold tracking-[-1.28px]" x-text="(data?.risk_score ?? '—') + '/100'"></span>
                        </div>
                        <p class="mt-0.5 text-[12px] capitalize text-muted">
                            Grade <span class="font-medium text-ink" x-text="data?.risk_grade ?? '—'"></span>
                            · <span x-text="data?.risk_classification ?? 'unknown'"></span>
                        </p>
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-md bg-[#fafafa] px-3 py-2.5 dark:bg-[#0b0f18]">
                        <dt class="v-kicker">Network</dt>
                        <dd class="mt-0.5 truncate text-[13px]" x-text="data?.isp || data?.organisation || 'Unavailable'"></dd>
                    </div>
                    <div class="rounded-md bg-[#fafafa] px-3 py-2.5 dark:bg-[#0b0f18]">
                        <dt class="v-kicker">ASN</dt>
                        <dd class="mt-0.5 truncate text-[13px]" x-text="data?.asn ? data.asn + (data.asn_organisation ? ' · ' + data.asn_organisation : '') : 'Unavailable'"></dd>
                    </div>
                    <div class="rounded-md bg-[#fafafa] px-3 py-2.5 dark:bg-[#0b0f18]">
                        <dt class="v-kicker">Connection</dt>
                        <dd class="mt-0.5 truncate text-[13px] capitalize" x-text="data?.classification || 'unknown'"></dd>
                    </div>
                    <div class="rounded-md bg-[#fafafa] px-3 py-2.5 dark:bg-[#0b0f18]">
                        <dt class="v-kicker">Abuse confidence</dt>
                        <dd class="mt-0.5 truncate text-[13px]" x-text="(data?.abuse_confidence ?? '—') + (data?.abuse_reports ? ' · ' + data.abuse_reports + ' reports' : '')"></dd>
                    </div>
                </dl>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="v-pill"><span class="v-dot" x-bind:class="data?.is_vpn ? 'v-dot-red' : 'v-dot-green'"></span>VPN <span x-text="data?.is_vpn ? 'detected' : 'clear'"></span></span>
                    <span class="v-pill"><span class="v-dot" x-bind:class="data?.is_proxy ? 'v-dot-red' : 'v-dot-green'"></span>Proxy <span x-text="data?.is_proxy ? 'detected' : 'clear'"></span></span>
                    <span class="v-pill"><span class="v-dot" x-bind:class="data?.is_tor ? 'v-dot-red' : 'v-dot-green'"></span>Tor <span x-text="data?.is_tor ? 'detected' : 'clear'"></span></span>
                </div>

                <p class="mt-4 border-t border-line/60 pt-3 text-[11px] leading-relaxed text-muted">
                    <span x-text="providerCount() + ' of ' + providerTotal() + ' sources responded'"></span>
                    · cached for 48 hours · missing fields stay empty rather than guessed.
                    <a href="{{ route('ip.check') }}" class="v-link ml-1">Full report →</a>
                </p>
            </div>
        </div>
    </section>

    {{-- TRUST STRIP --}}
    <section class="border-t border-line/70 py-8">
        <div class="grid grid-cols-2 gap-6 text-[12px] text-muted sm:grid-cols-4">
            <div class="flex items-center gap-2"><span class="v-dot v-dot-green"></span>Evidence stored per finding</div>
            <div class="flex items-center gap-2"><span class="v-dot v-dot-green"></span>Blocked sources read “unknown”</div>
            <div class="flex items-center gap-2"><span class="v-dot v-dot-green"></span>Consent required before scanning</div>
            <div class="flex items-center gap-2"><span class="v-dot v-dot-green"></span>Zero passwords requested</div>
        </div>
    </section>

    {{-- CAPABILITIES --}}
    <section id="capabilities" class="border-t border-line/70 py-16">
        <div class="max-w-2xl">
            <p class="v-kicker">What we check</p>
            <h2 class="mt-2 text-[clamp(1.6rem,2.6vw,2rem)] font-semibold tracking-[-1.28px]">
                A map of your public surface, not a stream of alerts.
            </h2>
            <p class="mt-3 text-[14px] leading-relaxed text-body">
                Four kinds of exposure, checked against public sources and recorded with the evidence that produced them.
            </p>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="v-card p-6">
                <div class="flex items-center gap-2"><span class="v-dot v-dot-blue"></span><p class="v-kicker">Public profiles</p></div>
                <h3 class="mt-3 text-[15px] font-medium">Where your handles surface</h3>
                <p class="mt-2 text-[13px] leading-relaxed text-body">
                    A curated catalog of public platforms is checked for each monitored username. Matches carry the URL, HTTP outcome and response hash.
                </p>
            </div>
            <div class="v-card p-6">
                <div class="flex items-center gap-2"><span class="v-dot v-dot-amber"></span><p class="v-kicker">Breach metadata</p></div>
                <h3 class="mt-3 text-[15px] font-medium">Exposure without your passwords</h3>
                <p class="mt-2 text-[13px] leading-relaxed text-body">
                    Breach metadata shows when an email appears in a public disclosure index. TraceLess never asks for, stores or tests a password.
                </p>
            </div>
            <div class="v-card p-6">
                <div class="flex items-center gap-2"><span class="v-dot v-dot-red"></span><p class="v-kicker">Data brokers</p></div>
                <h3 class="mt-3 text-[15px] font-medium">Listings that feed profiling</h3>
                <p class="mt-2 text-[13px] leading-relaxed text-body">
                    People-search directories and public registries are surfaced with the opt-out path that source actually supports.
                </p>
            </div>
            <div class="v-card p-6">
                <div class="flex items-center gap-2"><span class="v-dot v-dot-purple"></span><p class="v-kicker">Re-exposure watch</p></div>
                <h3 class="mt-3 text-[15px] font-medium">What comes back</h3>
                <p class="mt-2 text-[13px] leading-relaxed text-body">
                    Recurring scans watch for records that reappear after removal, so you know when a source re-published your data.
                </p>
            </div>
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section id="how-it-works" class="border-t border-line/70 py-16">
        <div class="max-w-2xl">
            <p class="v-kicker">How it works</p>
            <h2 class="mt-2 text-[clamp(1.6rem,2.6vw,2rem)] font-semibold tracking-[-1.28px]">Four steps, each reversible.</h2>
        </div>

        <ol class="mt-8 grid gap-4 md:grid-cols-4">
            <li class="v-card p-5">
                <p class="v-kicker">01</p>
                <h3 class="mt-2 text-[14px] font-medium">Authorise an identity</h3>
                <p class="mt-1.5 text-[12px] leading-relaxed text-body">Record your name, known handles and public emails behind an explicit consent step.</p>
            </li>
            <li class="v-card p-5">
                <p class="v-kicker">02</p>
                <h3 class="mt-2 text-[14px] font-medium">Run a scan</h3>
                <p class="mt-1.5 text-[12px] leading-relaxed text-body">Snapshot checks the curated catalog; deep scans extend to usernames, emails and phones.</p>
            </li>
            <li class="v-card p-5">
                <p class="v-kicker">03</p>
                <h3 class="mt-2 text-[14px] font-medium">Review the evidence</h3>
                <p class="mt-1.5 text-[12px] leading-relaxed text-body">Every finding shows how it was verified. Sources that blocked us read “unknown”, never invented.</p>
            </li>
            <li class="v-card p-5">
                <p class="v-kicker">04</p>
                <h3 class="mt-2 text-[14px] font-medium">Work the removals</h3>
                <p class="mt-1.5 text-[12px] leading-relaxed text-body">Follow the source-specific opt-out path and track what was sent, removed and re-exposed.</p>
            </li>
        </ol>
    </section>

    {{-- FREE BETA / PRICING --}}
    <section id="pricing" class="border-t border-line/70 py-16">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,0.75fr)]">
            <div class="v-card p-7">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="v-pill"><span class="v-dot v-dot-green"></span>v1.0 beta</span>
                    <span class="v-pill">No card required</span>
                </div>
                <h2 class="mt-4 text-[clamp(1.5rem,2.4vw,1.9rem)] font-semibold tracking-[-1.28px]">Everything is free during the beta.</h2>
                <p class="mt-3 max-w-xl text-[14px] leading-relaxed text-body">
                    Snapshot scans, deep scans and monitoring are open to every account while TraceLess is in v1.0.
                    When v2.0 arrives, paid plans will be announced in advance — nothing charges automatically and no card is collected now.
                </p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('register') }}" class="v-btn v-btn-primary">Create a free account</a>
                    <a href="{{ route('login') }}" class="v-btn v-btn-secondary">Sign in</a>
                </div>
            </div>

            <div class="v-card p-7">
                <p class="v-kicker">Roadmap</p>
                <ul class="mt-4 space-y-3 text-[13px] text-body">
                    <li class="flex items-start gap-2"><span class="v-dot v-dot-green mt-1.5"></span><span><span class="font-medium text-ink">v1.0 beta</span> — free scans, deep scans, monitoring and removals.</span></li>
                    <li class="flex items-start gap-2"><span class="v-dot v-dot-blue mt-1.5"></span><span><span class="font-medium text-ink">v2.0</span> — paid plans, billing and team workspaces.</span></li>
                    <li class="flex items-start gap-2"><span class="v-dot v-dot-gray mt-1.5"></span><span>Nothing in the beta will ever charge you automatically.</span></li>
                </ul>
            </div>
        </div>
    </section>

    {{-- PRIVACY PROMISE --}}
    <section class="border-t border-line/70 py-16">
        <div class="grid gap-8 lg:grid-cols-3">
            <div>
                <p class="v-kicker">Privacy by design</p>
                <h2 class="mt-2 text-[clamp(1.4rem,2.2vw,1.75rem)] font-semibold tracking-[-1px]">You stay in control of what is stored.</h2>
                <p class="mt-3 text-[13px] leading-relaxed text-body">
                    Export everything, purge scan data, or delete your account at any time from Settings.
                    Read the <a href="{{ route('privacy') }}" class="v-link">privacy policy</a> and
                    <a href="{{ route('security') }}" class="v-link">security notes</a>.
                </p>
            </div>
            <div class="v-card p-5">
                <p class="v-kicker">Passwords</p>
                <p class="mt-2 text-[13px] leading-relaxed text-body">Sign-in is a one-time email code. TraceLess never asks for a password, and never tests one against third-party logins.</p>
            </div>
            <div class="v-card p-5">
                <p class="v-kicker">Consent</p>
                <p class="mt-2 text-[13px] leading-relaxed text-body">Scanning requires a recorded consent event for the identity. Scans stop when you cancel, and stale jobs cannot resurrect a finished scan.</p>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="border-t border-line/70 py-16">
        <div class="v-card px-6 py-12 text-center sm:px-12">
            <p class="v-kicker">Start with one identity</p>
            <h2 class="mx-auto mt-2 max-w-2xl text-[clamp(1.7rem,3vw,2.25rem)] font-semibold leading-tight tracking-[-1.28px]">
                See what is public, then decide what to remove.
            </h2>
            <p class="mx-auto mt-3 max-w-lg text-[14px] leading-relaxed text-body">
                Free during the v1.0 beta. No password, no card, and nothing is scanned without your consent.
            </p>
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <a href="{{ route('register') }}" class="v-btn v-btn-primary">Start free</a>
                <a href="{{ route('password.check') }}" class="v-btn v-btn-secondary">Check a password first</a>
            </div>
        </div>
    </section>

</div>
@endsection
