@extends('layouts.marketing')

@section('title', 'TraceLess · Know what is public about you')

@section('content')
<div class="mx-auto max-w-[1200px] px-5 sm:px-8" data-landing>

    {{-- HERO --}}
    <section class="grid items-center gap-12 py-14 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)] lg:py-20">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="v-pill"><span class="v-dot v-dot-green"></span>v1.0 · Public beta</span>
                <span class="v-pill">Free during beta</span>
            </div>

            <h1 class="mt-6 max-w-xl text-[clamp(2.5rem,5vw,4rem)] font-semibold leading-[1.02] tracking-[-0.045em]" style="min-height: 3.1em">
                <span class="sr-only">Know what the internet already knows about you.</span>
                <span aria-hidden="true" class="bg-gradient-to-b from-[#171717] to-[#555] bg-clip-text text-transparent dark:from-white dark:to-[#9aa3b2]"><span data-typewriter data-text="Know what the internet already knows about you."></span><span data-typewriter-cursor class="tw-cursor is-typing" aria-hidden="true">|</span></span>
            </h1>

            <p class="subtitle-loop mt-5 max-w-lg text-[15px] leading-relaxed text-body">
                TraceLess finds the profiles, directories and public records that connect to your identity,
                proves each match against its source, and shows the evidence behind it. No passwords, ever.
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
                            this.startCountdown();
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
                },
                remaining: '',
                countdownTimer: null,
                startCountdown() {
                    clearInterval(this.countdownTimer);
                    const update = () => {
                        const target = this.data && this.data.expires_at ? new Date(this.data.expires_at).getTime() : NaN;
                        if (Number.isNaN(target)) { this.remaining = '—'; return; }
                        const ms = target - Date.now();
                        if (ms <= 0) { this.remaining = 'ready now'; clearInterval(this.countdownTimer); return; }
                        const hours = Math.floor(ms / 3600000);
                        const minutes = Math.floor((ms % 3600000) / 60000);
                        const seconds = Math.floor((ms % 60000) / 1000);
                        this.remaining = hours + 'h ' + minutes + 'm ' + (seconds < 10 ? '0' : '') + seconds + 's';
                    };
                    update();
                    this.countdownTimer = setInterval(update, 1000);
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
                <span class="v-kicker" x-text="state === 'ready' ? 'Live check' : (state === 'warming' ? 'Reading signals…' : 'Unavailable')"></span>
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
                    <span x-text="providerCount() > 0 ? providerCount() + ' of ' + providerTotal() + ' sources responded' : 'No public source responded yet'"></span>
                    · Cached for 48h · fresh scan in <span class="font-medium text-ink" x-text="remaining || '—'"></span>
                    <a href="{{ route('ip.check') }}" class="v-link ml-1">Full report →</a>
                </p>

                <p x-show="providerCount() === 0" class="mt-2 text-[11px] leading-relaxed text-warning">
                    The public intelligence sources are briefly unavailable. Retry in a moment, or check any IP manually.
                </p>
            </div>

            {{-- Device / browser check: client-side only, from browser-exposed APIs --}}
            <div class="v-card mt-4 p-5" data-device-check>
                <div class="flex items-center justify-between gap-2">
                    <p class="v-kicker">👀 We see you</p>
                    <span class="v-pill">Client-side</span>
                </div>
                <p class="mt-1 text-[12px] leading-relaxed text-muted">
                    What your browser volunteers before you sign up. It stays in your browser — nothing here is sent to TraceLess.
                </p>

                <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3">
                    <div class="min-w-0">
                        <dt class="v-kicker">Device</dt>
                        <dd class="mt-0.5 truncate text-[13px]" data-device-field="device">Detecting…</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="v-kicker">Browser</dt>
                        <dd class="mt-0.5 truncate text-[13px]" data-device-field="browser">Detecting…</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="v-kicker">Device spec</dt>
                        <dd class="mt-0.5 truncate text-[13px]" data-device-field="spec">Detecting…</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="v-kicker">Battery</dt>
                        <dd class="mt-0.5 truncate text-[13px]" data-device-field="battery">Checking…</dd>
                    </div>
                    <div class="col-span-2 min-w-0">
                        <dt class="v-kicker">Screen &amp; locale</dt>
                        <dd class="mt-0.5 truncate text-[13px]" data-device-field="screen">Detecting…</dd>
                    </div>
                </dl>

                <div class="v-progress mt-3" aria-hidden="true"><span data-battery-bar style="width: 0%"></span></div>
                <p class="mt-2 text-[10px] leading-relaxed text-muted">Battery is only shown when the browser exposes it. No fingerprint is stored.</p>
                <a href="#device-intel" class="v-link mt-2 inline-block">See the full browser fingerprint ↓</a>
            </div>
        </div>
    </section>

    {{-- FULL DEVICE / BROWSER PANEL --}}
    <section id="device-intel" class="border-y border-line/70 py-12" data-device-detail>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="v-kicker">👀 We see you</p>
                <h2 class="mt-1 text-[clamp(1.4rem,2.2vw,1.75rem)] font-semibold tracking-[-1px]">What your browser reveals before you sign up.</h2>
                <p class="mt-2 max-w-2xl text-[13px] leading-relaxed text-body">
                    Read locally with the MIT-licensed <span class="font-medium text-ink">@marufme/device-key</span> package plus plain Web APIs.
                    Nothing on this panel is sent to TraceLess or persisted.
                </p>
            </div>
            <span class="v-pill">Client-side · not stored</span>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {{-- System & browser --}}
            <div class="v-card p-5">
                <p class="v-kicker">System &amp; browser</p>
                <dl class="mt-3 space-y-2.5 text-[13px]">
                    <div><dt class="v-kicker">Browser · engine · vendor</dt><dd class="mt-0.5 break-words" data-device-field="d-browser">Reading…</dd></div>
                    <div><dt class="v-kicker">Operating system</dt><dd class="mt-0.5" data-device-field="d-os">Reading…</dd></div>
                    <div><dt class="v-kicker">Device type</dt><dd class="mt-0.5" data-device-field="d-type">Reading…</dd></div>
                    <div><dt class="v-kicker">Device model</dt><dd class="mt-0.5 break-words" data-device-field="d-model">Reading…</dd></div>
                    <div><dt class="v-kicker">Language &amp; timezone</dt><dd class="mt-0.5" data-device-field="d-locale">Reading…</dd></div>
                    <div><dt class="v-kicker">User agent</dt><dd class="mt-0.5 break-all font-[family-name:'Geist_Mono',ui-monospace,monospace] text-[11px] leading-relaxed text-muted" data-device-field="d-ua">Reading…</dd></div>
                </dl>
            </div>

            {{-- Hardware & display --}}
            <div class="v-card p-5">
                <p class="v-kicker">Hardware &amp; display</p>
                <dl class="mt-3 space-y-2.5 text-[13px]">
                    <div><dt class="v-kicker">CPU</dt><dd class="mt-0.5" data-device-field="d-cpu">Reading…</dd></div>
                    <div><dt class="v-kicker">Memory (approximate)</dt><dd class="mt-0.5" data-device-field="d-memory">Reading…</dd></div>
                    <div><dt class="v-kicker">GPU</dt><dd class="mt-0.5 break-words" data-device-field="d-gpu">Reading…</dd></div>
                    <div><dt class="v-kicker">GPU capabilities</dt><dd class="mt-0.5 text-[11px] text-muted" data-device-field="d-gpu-cap">Reading…</dd></div>
                    <div><dt class="v-kicker">Display</dt><dd class="mt-0.5" data-device-field="d-display">Reading…</dd></div>
                    <div>
                        <dt class="v-kicker">Battery</dt>
                        <dd class="mt-0.5" data-device-field="d-battery">Reading…</dd>
                        <div class="v-progress mt-2" aria-hidden="true"><span data-battery-bar style="width: 0%"></span></div>
                    </div>
                </dl>
            </div>

            {{-- Network, storage & fingerprint --}}
            <div class="v-card p-5">
                <p class="v-kicker">Network, storage &amp; fingerprint</p>
                <dl class="mt-3 space-y-2.5 text-[13px]">
                    <div><dt class="v-kicker">Network</dt><dd class="mt-0.5 break-words" data-device-field="d-network">Reading…</dd></div>
                    <div><dt class="v-kicker">Storage</dt><dd class="mt-0.5 break-words" data-device-field="d-storage">Reading…</dd></div>
                    <div><dt class="v-kicker">Capabilities</dt><dd class="mt-0.5 break-words" data-device-field="d-capabilities">Reading…</dd></div>
                    <div><dt class="v-kicker">Fingerprint (canvas + device key)</dt><dd class="mt-0.5 break-all font-[family-name:'Geist_Mono',ui-monospace,monospace] text-[11px]" data-device-field="d-fingerprint">Reading…</dd></div>
                    <div><dt class="v-kicker">Local device id (not sent)</dt><dd class="mt-0.5 break-all font-[family-name:'Geist_Mono',ui-monospace,monospace] text-[11px]" data-device-field="d-device-id">Reading…</dd></div>
                </dl>
            </div>
        </div>

        <div class="v-card mt-4 p-4">
            <p class="v-kicker">Why some fields say “Not exposed”</p>
            <p class="mt-1 text-[12px] leading-relaxed text-muted" data-device-privacy-note>Checking your browser's privacy posture…</p>
        </div>
    </section>

    {{-- PRODUCT FILM — full-bleed background with licensed water-ripple distortion --}}
    <section class="film-bleed border-y border-line/70">
        <div class="film-stage" data-ripple>
            <video autoplay muted loop playsinline preload="metadata" aria-label="TraceLess product film" class="film-video">
                <source src="{{ asset('assets/videos/hero-intro.mp4') }}" type="video/mp4">
            </video>
            <div class="film-shade" aria-hidden="true"></div>
            <div class="film-caption mx-auto flex w-full max-w-[1200px] flex-col justify-end px-5 pb-8 sm:px-8 sm:pb-10">
                <p class="text-[10px] uppercase tracking-[0.18em] text-white/70">Product film</p>
                <div class="mt-1 flex flex-wrap items-end justify-between gap-3">
                    <p class="max-w-xl text-xl font-medium leading-snug text-white sm:text-2xl">How TraceLess finds what is public — and what it refuses to guess.</p>
                    <span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] text-white/80 backdrop-blur">Move your cursor across the film</span>
                </div>
            </div>
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-2 py-3 text-[11px] text-muted">
        <span>👀 Two licensed crawlers are loose on this page. They chew words, not people.</span>
        <span>Hover to lure them · click to send them flying to a word</span>
    </div>

    {{-- TRUST STRIP (marquee) --}}
    <section class="border-y border-line/70 py-4" aria-label="Product guarantees">
        <div class="marquee">
            <div class="marquee-track text-[12px] text-muted" aria-hidden="false">
                @foreach([1, 2] as $loopIndex)
                    <div class="{{ $loopIndex === 2 ? 'contents' : 'contents' }}">
                        <span class="flex items-center gap-2 whitespace-nowrap"><span class="v-dot v-dot-green"></span>Evidence stored per finding</span>
                        <span class="flex items-center gap-2 whitespace-nowrap"><span class="v-dot v-dot-green"></span>Blocked sources read “unknown”</span>
                        <span class="flex items-center gap-2 whitespace-nowrap"><span class="v-dot v-dot-green"></span>Consent required before scanning</span>
                        <span class="flex items-center gap-2 whitespace-nowrap"><span class="v-dot v-dot-green"></span>Zero passwords requested</span>
                        <span class="flex items-center gap-2 whitespace-nowrap"><span class="v-dot v-dot-green"></span>No card required during beta</span>
                    </div>
                @endforeach
            </div>
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

        <div class="mt-8" x-data="{
                scrollByCard(direction) {
                    const track = this.$refs.track;
                    const card = track.querySelector('[data-card]');
                    const amount = card ? card.getBoundingClientRect().width + 16 : 340;
                    track.scrollBy({ left: direction * amount, behavior: 'smooth' });
                }
             }">
            <div class="flex items-center justify-between gap-3">
                <p class="text-[12px] text-muted">Slide to see every check</p>
                <div class="flex gap-1.5">
                    <button type="button" class="v-btn v-btn-secondary h-9 w-9 !px-0" x-on:click="scrollByCard(-1)" aria-label="Previous check">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </button>
                    <button type="button" class="v-btn v-btn-secondary h-9 w-9 !px-0" x-on:click="scrollByCard(1)" aria-label="Next check">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </button>
                </div>
            </div>

            <div x-ref="track" class="no-scrollbar mt-3 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-2">
                <div data-card class="v-card w-[86%] flex-none snap-start p-6 sm:w-[48%] lg:w-[31.5%]">
                    <div class="flex items-center gap-2"><span class="v-dot v-dot-blue"></span><p class="v-kicker">Public profiles</p></div>
                    <h3 class="mt-3 text-[15px] font-medium">Where your handles surface</h3>
                    <p class="mt-2 text-[13px] leading-relaxed text-body">
                        A curated catalog of public platforms is checked for each monitored username. Matches carry the URL, HTTP outcome and response hash.
                    </p>
                </div>
                <div data-card class="v-card w-[86%] flex-none snap-start p-6 sm:w-[48%] lg:w-[31.5%]">
                    <div class="flex items-center gap-2"><span class="v-dot v-dot-amber"></span><p class="v-kicker">Breach metadata</p></div>
                    <h3 class="mt-3 text-[15px] font-medium">Exposure without your passwords</h3>
                    <p class="mt-2 text-[13px] leading-relaxed text-body">
                        Breach metadata shows when an email appears in a public disclosure index. TraceLess never asks for, stores or tests a password.
                    </p>
                </div>
                <div data-card class="v-card w-[86%] flex-none snap-start p-6 sm:w-[48%] lg:w-[31.5%]">
                    <div class="flex items-center gap-2"><span class="v-dot v-dot-red"></span><p class="v-kicker">Data brokers</p></div>
                    <h3 class="mt-3 text-[15px] font-medium">Listings that feed profiling</h3>
                    <p class="mt-2 text-[13px] leading-relaxed text-body">
                        People-search directories and public registries are surfaced with the opt-out path that source actually supports.
                    </p>
                </div>
                <div data-card class="v-card w-[86%] flex-none snap-start p-6 sm:w-[48%] lg:w-[31.5%]">
                    <div class="flex items-center gap-2"><span class="v-dot v-dot-purple"></span><p class="v-kicker">Re-exposure watch</p></div>
                    <h3 class="mt-3 text-[15px] font-medium">What comes back</h3>
                    <p class="mt-2 text-[13px] leading-relaxed text-body">
                        Recurring scans watch for records that reappear later, so you know when a source re-published your data.
                    </p>
                </div>
                <div data-card class="v-card w-[86%] flex-none snap-start p-6 sm:w-[48%] lg:w-[31.5%]">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2"><span class="v-dot v-dot-gray"></span><p class="v-kicker">Planned · v2.0</p></div>
                        <span class="v-pill">v2.0</span>
                    </div>
                    <h3 class="mt-3 text-[15px] font-medium">Image and face deep scans</h3>
                    <p class="mt-2 text-[13px] leading-relaxed text-body">
                        Reverse-image and facial-exposure searches across public sources are planned for v2.0. Not shipped yet — we do not fake coverage.
                    </p>
                </div>
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
                <h3 class="mt-2 text-[14px] font-medium">Act on the evidence</h3>
                <p class="mt-1.5 text-[12px] leading-relaxed text-body">Decide what should change, follow the source-specific guidance, and keep a record of what you did.</p>
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
                    <li class="flex items-start gap-2"><span class="v-dot v-dot-blue mt-1.5"></span><span><span class="font-medium text-ink">v2.0</span> — paid plans, billing, team workspaces, and image + face deep scans.</span></li>
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
                See what is public, then decide what changes.
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
