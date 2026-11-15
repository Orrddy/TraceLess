<div class="space-y-6">
    <x-page-header eyebrow="Continuous watch" title="Monitoring" description="Recurring scans watch for new exposures, reappearing records and credential leaks across your monitored identity.">
        @if($isDeepScan)
            <span class="badge badge-emerald"><span class="material-symbols-outlined text-[12px]">verified</span>Deep Scan active</span>
        @else
            <span class="badge badge-neutral"><span class="material-symbols-outlined text-[12px]">lock</span>Deep Scan feature</span>
        @endif
    </x-page-header>

    @if(!$profile)
        <x-empty-state icon="fingerprint" title="No identity to monitor" description="Create an identity profile first. Monitoring only watches identities you own and have consented to scan.">
            <a href="{{ route('identity.create') }}" class="btn-primary">Create identity</a>
        </x-empty-state>
    @else
        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Monitoring configuration --}}
            <div class="glass-card p-6 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="mono-label">Watch status</p>
                        <p class="mt-2 font-editorial text-3xl text-ink">
                            {{ $isActive ? 'Watching' : 'Paused' }}
                            <span class="ml-2 inline-flex h-2.5 w-2.5 align-middle rounded-full {{ $isActive ? 'bg-emerald animate-pulse' : 'bg-muted/40' }}"></span>
                        </p>
                        <p class="mt-1 font-mono text-[10px] text-muted">
                            {{ $profile->full_name }} · {{ $profile->primary_email }}
                        </p>
                    </div>

                    <button type="button" role="switch" aria-checked="{{ $isActive ? 'true' : 'false' }}"
                            wire:click="$toggle('isActive')"
                            class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border transition {{ $isActive ? 'border-electric-blue bg-electric-blue' : 'border-hairline bg-ink/10' }}">
                        <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ $isActive ? 'translate-x-6' : 'translate-x-1' }}"></span>
                        <span class="sr-only">Toggle recurring monitoring</span>
                    </button>
                </div>

                @if(!$isDeepScan && !$demoMode)
                    <div class="mt-5 rounded-xl border border-amber/25 bg-amber/10 px-4 py-3">
                        <p class="font-mono text-[10px] font-semibold text-[#8a5c00]">Recurring monitoring requires Deep Scan</p>
                        <p class="mt-1 font-body text-xs leading-relaxed text-[#8a5c00]/85">You can configure preferences now, but scheduled scans only run on an active Deep Scan plan.</p>
                    </div>
                @endif

                <div class="mt-6">
                    <p class="mono-label mb-2">Scan cadence</p>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach(['daily' => ['Daily', 'Highest signal, most requests'], 'weekly' => ['Weekly', 'Recommended balance'], 'monthly' => ['Monthly', 'Light-touch monitoring']] as $value => [$label, $hint])
                            <button type="button" wire:click="$set('cadence', '{{ $value }}')"
                                    class="rounded-2xl border p-4 text-left transition {{ $cadence === $value ? 'border-electric-blue/40 bg-electric-blue/[.06] shadow-glass-sm' : 'border-hairline bg-white/60 hover:bg-white' }}">
                                <span class="flex items-center justify-between">
                                    <span class="font-body text-sm font-medium text-ink">{{ $label }}</span>
                                    <span class="material-symbols-outlined text-[16px] {{ $cadence === $value ? 'text-electric-blue' : 'text-muted' }}">{{ $cadence === $value ? 'radio_button_checked' : 'radio_button_unchecked' }}</span>
                                </span>
                                <span class="mt-1 block font-body text-[11px] leading-snug text-muted">{{ $hint }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 border-t border-hairline pt-5">
                    <p class="mono-label mb-3">Alert me about</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach([
                            'new_profiles' => 'New public profiles',
                            'contact_info' => 'Contact info published',
                            'data_brokers' => 'Data broker listings',
                            'reappearing' => 'Reappearing records',
                            'credentials' => 'Credential leaks',
                            'impersonation' => 'Impersonation signals',
                        ] as $key => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-hairline bg-white/60 px-3 py-2.5 transition hover:bg-white">
                                <input type="checkbox" wire:model="options.{{ $key }}" class="rounded border-hairline text-electric-blue focus:ring-electric-blue/30">
                                <span class="font-body text-xs text-charcoal">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @error('isActive')<p class="mt-3 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror

                <div class="mt-6 flex flex-wrap items-center gap-2 border-t border-hairline pt-5">
                    <button wire:click="save" class="btn-primary">
                        <span class="material-symbols-outlined text-[16px]">save</span> Save preferences
                    </button>
                    @if($demoMode && !$isDeepScan)
                        <button wire:click="enableDemoDeepScan" class="btn-secondary">Enable demo Deep Scan</button>
                    @endif
                    @if($isDeepScan)
                        <button wire:click="runNow" class="btn-secondary" wire:loading.attr="disabled">
                            <span class="material-symbols-outlined text-[16px]">bolt</span> Run monitoring scan now
                        </button>
                    @endif
                </div>
            </div>

            {{-- Schedule summary --}}
            <div class="space-y-5">
                <div class="glass-card p-5">
                    <p class="mono-label">Schedule</p>
                    <dl class="mt-4 space-y-3 font-body text-xs">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">Cadence</dt>
                            <dd class="font-medium capitalize text-ink">{{ $cadence }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">Last scan</dt>
                            <dd class="font-mono text-[10px] text-ink">{{ $subscription?->last_scanned_at?->format('d M Y') ?? 'Never' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-muted">Next scan</dt>
                            <dd class="font-mono text-[10px] text-ink">{{ $subscription?->is_active ? $subscription->nextScanLabel() : 'Paused' }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 rounded-xl border border-hairline bg-canvas-alt px-3 py-2.5">
                        <p class="font-mono text-[9px] uppercase tracking-wider text-muted">Scheduler</p>
                        <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">A queue worker plus the Laravel scheduler must be running for automatic scans to fire.</p>
                    </div>
                </div>

                <div class="glass-card p-5">
                    <p class="mono-label">Signal ledger</p>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div class="glass-inset p-3">
                            <p class="font-mono text-2xl font-semibold text-ink">{{ $alerts->where('is_read', false)->count() }}</p>
                            <p class="mt-1 font-body text-[10px] text-muted">Unread alerts</p>
                        </div>
                        <div class="glass-inset p-3">
                            <p class="font-mono text-2xl font-semibold text-ink">{{ $alerts->count() }}</p>
                            <p class="mt-1 font-body text-[10px] text-muted">Recent signals</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Alerts --}}
        <div class="glass-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-hairline px-5 py-4">
                <div>
                    <p class="mono-label">Alert stream</p>
                    <h2 class="mt-0.5 font-editorial text-xl text-ink">What changed</h2>
                </div>
                @if($alerts->where('is_read', false)->isNotEmpty())
                    <button wire:click="markAllAlertsRead" class="btn-secondary !px-3 !py-2 !text-[10px]">Mark all read</button>
                @endif
            </div>

            <div class="divide-y divide-hairline">
                @forelse($alerts as $alert)
                    <div class="flex items-start gap-3 px-5 py-4 {{ $alert->is_read ? 'opacity-70' : '' }}">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border
                            {{ $alert->severity === 'critical' || $alert->severity === 'high' ? 'border-coral/25 bg-coral/10 text-coral' : ($alert->severity === 'moderate' ? 'border-amber/25 bg-amber/10 text-[#a86f00]' : 'border-hairline bg-white text-electric-blue') }}">
                            <span class="material-symbols-outlined text-[16px]">
                                {{ $alert->severity === 'critical' || $alert->severity === 'high' ? 'warning' : 'notifications' }}
                            </span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-body text-sm font-medium text-ink">{{ $alert->title }}</p>
                                @unless($alert->is_read)<span class="h-1.5 w-1.5 rounded-full bg-electric-blue"></span>@endunless
                            </div>
                            <p class="mt-1 font-body text-xs leading-relaxed text-muted">{{ $alert->message }}</p>
                            <p class="mt-1 font-mono text-[9px] uppercase tracking-wider text-muted/80">{{ $alert->type }} · {{ $alert->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @if($alert->action_url)
                                <a href="{{ $alert->action_url }}" class="btn-ghost !px-2 !py-1 !text-[10px]">Open</a>
                            @endif
                            @unless($alert->is_read)
                                <button wire:click="markAlertRead({{ $alert->id }})" class="btn-ghost !px-2 !py-1 !text-[10px]" aria-label="Mark alert read">
                                    <span class="material-symbols-outlined text-[15px]">done</span>
                                </button>
                            @endunless
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <span class="material-symbols-outlined text-3xl text-muted">sensors_off</span>
                        <p class="mt-2 font-mono text-xs text-muted">No alerts yet. Once monitoring runs, changes appear here.</p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
