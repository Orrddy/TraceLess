<div class="space-y-6">
    @php
        $flag = null;
        $code = $result['country_code'] ?? null;
        if (is_string($code) && strlen($code) === 2) {
            $flag = collect(str_split(strtoupper($code)))
                ->map(fn ($c) => mb_chr(127397 + ord($c)))
                ->implode('');
        }
        $tone = match ($result['risk_classification'] ?? null) {
            'low' => ['text-[#398E4A]', 'v-dot-green'],
            'moderate' => ['text-[#FF990A]', 'v-dot-amber'],
            'high', 'critical' => ['text-[#E5484D]', 'v-dot-red'],
            default => ['text-ink', 'v-dot-gray'],
        };
        $providerLabels = [
            'ipapi_is' => 'ipapi.is',
            'proxycheck' => 'proxycheck.io',
            'abuseipdb' => 'AbuseIPDB',
            'ip2location' => 'IP2Location.io',
        ];
        $providerTone = fn (?string $status): string => match ($status) {
            'ok' => 'v-dot-green',
            'timeout' => 'v-dot-amber',
            'error' => 'v-dot-red',
            default => 'v-dot-gray',
        };
    @endphp

    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="v-kicker">Tools</p>
            <h1 class="v-h1 mt-1">Network intelligence</h1>
            <p class="v-small mt-1.5 max-w-2xl">
                What four public intelligence sources can tell about an IP address — location, network, anonymity signals and abuse reputation, each field attributed and never guessed.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if(config('traceless.ip_intelligence') !== null)
                <span class="v-pill"><span class="v-dot v-dot-green"></span>4 sources</span>
            @endif
            <span class="v-pill">48h cache</span>
        </div>
    </header>

    <section class="v-card p-5 sm:p-6">
        <form wire:submit="check" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" novalidate>
            <div class="min-w-0 flex-1">
                <label for="ip" class="v-kicker mb-1.5 block">IP address (IPv4 or IPv6)</label>
                <input id="ip" type="text" wire:model="ip" inputmode="text" class="input font-[family-name:'Geist_Mono',ui-monospace,monospace]"
                       autocomplete="off" spellcheck="false" placeholder="203.0.113.10" aria-describedby="ip-help">
                <p id="ip-help" class="mt-2 text-[12px] leading-relaxed text-muted">
                    Private, loopback and reserved addresses are rejected because they have no public network record.
                </p>
                @error('ip') <p class="mt-1 text-[12px] text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                <button type="submit"
                        class="v-btn v-btn-primary min-w-[118px] justify-center whitespace-nowrap"
                        wire:loading.attr="disabled" wire:target="check">
                    <span wire:loading.remove wire:target="check">Run check</span>
                    <span wire:loading wire:target="check">Checking…</span>
                </button>
                <button type="button"
                        class="v-btn v-btn-secondary min-w-[112px] justify-center whitespace-nowrap"
                        wire:click="useMyIp">Use my IP</button>
            </div>
        </form>
    </section>

    @if($error)
        <section class="v-card border-l-2 border-l-[#E5484D] p-5" role="alert">
            <p class="text-[13px] font-medium text-[#E5484D]">The check could not complete</p>
            <p class="mt-1 text-[13px] leading-relaxed text-body">{{ $error }}</p>
            @if($state === 'unavailable')
                <p class="mt-2 text-[12px] leading-relaxed text-muted">
                    Operators can confirm provider configuration in the environment; the landing page shows the same honest state instead of inventing a result.
                </p>
            @endif
        </section>
    @endif

    @if($state === 'warming')
        <section class="v-card p-6 sm:p-8" wire:poll.2s="refresh" aria-live="polite">
            <div class="flex items-start gap-4">
                <span class="mt-1.5 inline-flex h-2.5 w-2.5 animate-pulse rounded-full bg-[#0072F5]"></span>
                <div>
                    <p class="text-[14px] font-medium">Analysing {{ $ip }} across four sources</p>
                    <p class="mt-1 text-[13px] leading-relaxed text-body">
                        The public providers are queried in parallel in the background; this page updates automatically.
                        Results are cached for 48 hours when all sources answer, and for a much shorter window on partial answers.
                    </p>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        @foreach(['ipapi.is', 'proxycheck.io', 'AbuseIPDB', 'IP2Location.io'] as $provider)
                            <div class="flex items-center gap-2 rounded-md bg-[#fafafa] px-3 py-2 text-[12px] text-muted dark:bg-[#0b0f18]">
                                <span class="v-dot v-dot-gray animate-pulse"></span>{{ $provider }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if($state === 'ready' && $result)
        {{-- Risk headline --}}
        <section class="grid gap-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1.85fr)]">
            <div class="v-card p-6">
                <div class="flex items-center justify-between">
                    <p class="v-kicker">IP risk</p>
                    <span class="v-dot {{ $tone[1] }}"></span>
                </div>
                <div class="mt-3 flex items-end gap-2">
                    <span class="text-[3.2rem] font-semibold leading-none tracking-[-2px]">{{ $result['risk_score'] ?? '—' }}</span>
                    <span class="mb-1 text-[13px] text-muted">/ 100</span>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="v-pill {{ $tone[0] }}">Grade {{ $result['risk_grade'] ?? '—' }}</span>
                    <span class="v-pill capitalize {{ $tone[0] }}">{{ $result['risk_classification'] ?? 'unknown' }}</span>
                    <span class="v-pill">Confidence {{ $result['confidence'] ?? 0 }}%</span>
                </div>
                <div class="v-progress mt-4"><span style="width: {{ min(100, max(0, (int) ($result['risk_score'] ?? 0))) }}%"></span></div>
                <p class="mt-3 text-[12px] leading-relaxed text-muted">
                    Weighted from provider signals, not an average of provider scores. Missing coverage raises uncertainty rather than reading as clean.
                </p>

                <div class="mt-4 rounded-md bg-[#fafafa] px-3 py-2.5 dark:bg-[#0b0f18]"
                     x-data="{
                        remaining: '—',
                        timer: null,
                        init() {
                            const target = new Date(this.$el.dataset.expires || '');
                            if (Number.isNaN(target.getTime())) return;
                            const tick = () => {
                                const ms = target - Date.now();
                                if (ms <= 0) { this.remaining = 'ready now'; clearInterval(this.timer); return; }
                                const h = Math.floor(ms / 3600000);
                                const m = Math.floor((ms % 3600000) / 60000);
                                const s = Math.floor((ms % 60000) / 1000);
                                this.remaining = h + 'h ' + m + 'm ' + (s < 10 ? '0' : '') + s + 's';
                            };
                            tick();
                            this.timer = setInterval(tick, 1000);
                        },
                        destroy() { clearInterval(this.timer); }
                     }"
                     x-init="init()"
                     data-expires="{{ $result['expires_at'] ?? '' }}">
                    <p class="v-kicker">Cache</p>
                    <p class="mt-1 text-[13px] leading-relaxed">
                        Cached for 48h · fresh scan in <span class="font-medium text-ink" x-text="remaining"></span>
                    </p>
                    <p class="mt-1 text-[11px] leading-relaxed text-muted">Fresh scans reuse the cached record until it expires, which keeps provider quotas for new addresses.</p>
                </div>

                <div class="mt-4 border-t border-line/60 pt-4">
                    <p class="v-kicker">Source coverage</p>
                    <div class="mt-2 space-y-2">
                        @foreach($providerLabels as $key => $label)
                            @php $status = $result['provider_status'][$key] ?? 'unavailable'; @endphp
                            <div class="flex items-center justify-between text-[12px]">
                                <span class="flex items-center gap-2 text-body"><span class="v-dot {{ $providerTone($status) }}"></span>{{ $label }}</span>
                                <span class="capitalize text-muted">{{ $status }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Identity + network --}}
            <div class="v-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="v-kicker">Address</p>
                        <p class="mt-1 break-all font-[family-name:'Geist_Mono',ui-monospace,monospace] text-xl font-medium">{{ $result['ip'] }}</p>
                        <p class="mt-1 text-[13px] text-body">
                            {{ $flag }} {{ collect([$result['city'] ?? null, $result['region'] ?? null, $result['country'] ?? null])->filter()->implode(', ') ?: 'Location not reported' }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="v-kicker">Type</p>
                        <p class="mt-1 text-[13px]">IPv{{ $result['ip_version'] ?? '—' }} · <span class="capitalize">{{ $result['classification'] ?? 'unknown' }}</span></p>
                        <p class="mt-1 text-[12px] text-muted">{{ $result['connection_type'] ?? 'connection not reported' }}</p>
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-line/60 pt-4 sm:grid-cols-3">
                    @foreach([
                        ['ISP', $result['isp'] ?? null],
                        ['Organisation', $result['organisation'] ?? null],
                        ['ASN', $result['asn'] ?? null],
                        ['ASN organisation', $result['asn_organisation'] ?? null],
                        ['Network range', $result['network_range'] ?? null],
                        ['Timezone', $result['timezone'] ?? null],
                        ['Postal code', $result['postal_code'] ?? null],
                        ['Coordinates', isset($result['latitude'], $result['longitude']) ? $result['latitude'].', '.$result['longitude'] : null],
                        ['Datacenter', isset($result['datacenter']) ? ($result['datacenter'] ? 'Yes' : 'No') : null],
                    ] as [$label, $value])
                        <div class="min-w-0">
                            <dt class="v-kicker">{{ $label }}</dt>
                            <dd class="mt-0.5 truncate text-[13px]" title="{{ $value }}">{{ $value ?: 'Unavailable' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- Anonymity + abuse + reasons --}}
        <section class="grid gap-4 lg:grid-cols-3">
            <div class="v-card p-6">
                <p class="v-kicker">Anonymity signals</p>
                <div class="mt-3 space-y-2.5">
                    @foreach([
                        ['VPN', $result['is_vpn'] ?? null],
                        ['Proxy', $result['is_proxy'] ?? null],
                        ['Tor exit', $result['is_tor'] ?? null],
                        ['Residential proxy', $result['is_residential_proxy'] ?? null],
                        ['Open proxy', $result['is_open_proxy'] ?? null],
                        ['Hosting proxy', $result['is_hosting_proxy'] ?? null],
                        ['Anonymous connection', $result['anonymous'] ?? null],
                    ] as [$label, $flagValue])
                        <div class="flex items-center justify-between text-[13px]">
                            <span class="text-body">{{ $label }}</span>
                            <span class="flex items-center gap-2 {{ $flagValue === true ? 'text-[#E5484D]' : 'text-muted' }}">
                                <span class="v-dot {{ $flagValue === true ? 'v-dot-red' : 'v-dot-green' }}"></span>
                                {{ $flagValue === null ? 'Unknown' : ($flagValue ? 'Detected' : 'Clear') }}
                            </span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 border-t border-line/60 pt-3 text-[11px] leading-relaxed text-muted">
                    “Clear” means the responding providers reported no signal — not a guarantee of legitimacy.
                </p>
            </div>

            <div class="v-card p-6">
                <p class="v-kicker">Abuse reputation</p>
                <div class="mt-3 flex items-end gap-2">
                    <span class="text-[2.25rem] font-semibold leading-none tracking-[-1.28px]">{{ $result['abuse_confidence'] ?? '—' }}</span>
                    <span class="mb-1 text-[12px] text-muted">confidence / 100</span>
                </div>
                <dl class="mt-4 space-y-2.5 text-[13px]">
                    <div class="flex items-center justify-between"><dt class="text-body">Reports</dt><dd>{{ $result['abuse_reports'] ?? '—' }}</dd></div>
                    <div class="flex items-center justify-between"><dt class="text-body">Last reported</dt><dd>{{ $result['abuse_last_reported'] ?? 'Never' }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-body">Categories</dt><dd class="text-right">{{ ! empty($result['abuse_categories']) ? implode(', ', $result['abuse_categories']) : 'None reported' }}</dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-body">Blacklist</dt><dd class="text-right">{{ ! empty($result['blacklist']) ? implode(', ', $result['blacklist']) : 'None reported' }}</dd></div>
                </dl>
            </div>

            <div class="v-card p-6">
                <p class="v-kicker">Why this score</p>
                @if(! empty($result['reasons']))
                    <ul class="mt-3 space-y-2 text-[13px] leading-relaxed text-body">
                        @foreach(array_slice($result['reasons'], 0, 8) as $reason)
                            <li class="flex items-start gap-2"><span class="mt-1.5 v-dot v-dot-gray"></span><span>{{ $reason }}</span></li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-3 text-[13px] leading-relaxed text-body">No individual risk reasons were raised by the responding sources.</p>
                @endif

                <dl class="mt-4 space-y-2 border-t border-line/60 pt-3 text-[12px]">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Analysed</dt><dd class="text-right">{{ $result['analyzed_at'] ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Cache expires</dt><dd class="text-right">{{ $result['expires_at'] ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Sources</dt><dd class="text-right">{{ implode(', ', $result['sources'] ?? []) ?: 'None' }}</dd></div>
                </dl>
            </div>
        </section>
    @endif

    <section class="v-card p-6">
        <div class="grid gap-6 md:grid-cols-3">
            <div>
                <p class="v-kicker">What this is not</p>
                <p class="mt-2 text-[13px] leading-relaxed text-body">
                    This is network and reputation intelligence, not proof of identity or intent. A clean IP does not make a connection safe, and a poor score does not prove fraud.
                </p>
            </div>
            <div>
                <p class="v-kicker">How it is built</p>
                <p class="mt-2 text-[13px] leading-relaxed text-body">
                    Four providers are queried in parallel with strict timeouts; one failure never fabricates the others' fields. Results are cached for 48 hours and reused for every visitor sharing the address.
                </p>
            </div>
            <div>
                <p class="v-kicker">Privacy</p>
                <p class="mt-2 text-[13px] leading-relaxed text-body">
                    The page sends the queried address to the providers. TraceLess stores the analysis (not visitors' browsing) and never logs the raw address in application logs.
                </p>
            </div>
        </div>
    </section>
</div>
