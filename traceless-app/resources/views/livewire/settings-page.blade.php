<div class="space-y-6">
    <x-page-header eyebrow="Account controls" title="Settings" description="Manage your account, security, data retention and export. You remain in control of every stored record." />

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Profile --}}
        <form wire:submit="updateProfile" class="glass-card p-6 lg:col-span-2">
            <p class="mono-label">Identity</p>
            <h2 class="mt-1 font-editorial text-xl text-ink">Account profile</h2>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="settingsName" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-muted">Display name</label>
                    <input id="settingsName" type="text" wire:model="name" class="input-base" autocomplete="name">
                    @error('name')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="settingsEmail" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-muted">Sign-in email</label>
                    <input id="settingsEmail" type="email" wire:model="email" class="input-base" autocomplete="email">
                    @error('email')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
                    <p class="mt-1.5 font-body text-[11px] text-muted">Changing this sends a verification code to the new address.</p>
                </div>
            </div>

            @if($email_change_sent && $pending_email)
                <div class="mt-5 rounded-xl border border-electric-blue/25 bg-electric-blue/[.04] p-4">
                    <p class="font-body text-sm font-medium text-ink">Confirm your new email</p>
                    <p class="mt-1 font-body text-xs leading-relaxed text-muted">Enter the 6-digit code sent to <span class="font-medium text-ink">{{ $pending_email }}</span>. Your current email stays active until it is confirmed.</p>

                    @if($email_change_demo_code)
                        <div class="mt-3 rounded-lg border border-amber/30 bg-amber/[.07] px-3 py-2">
                            <p class="font-mono text-[9px] uppercase tracking-wider text-[#9A6700]">Demo mode — code shown locally</p>
                            <p class="mt-0.5 font-mono text-xl font-semibold tracking-[0.3em] text-ink">{{ $email_change_demo_code }}</p>
                        </div>
                    @endif

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input type="text" wire:model="email_change_code" inputmode="numeric" maxlength="6" placeholder="000000"
                               class="input-base font-mono tracking-[0.3em]">
                        <button type="button" wire:click="confirmEmailChange" class="btn-primary whitespace-nowrap">Confirm email</button>
                        <button type="button" wire:click="cancelEmailChange" class="btn-secondary whitespace-nowrap">Cancel</button>
                    </div>
                    @error('email_change_code')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
                </div>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                    <span class="material-symbols-outlined text-[16px]">save</span> Save profile
                </button>
                <span class="font-mono text-[10px] text-muted">Last login {{ $user->last_login_at?->diffForHumans() ?? 'unknown' }}</span>
            </div>
        </form>

        {{-- Plan --}}
        <div class="glass-card p-6">
            <p class="mono-label">Plan</p>
            <h2 class="mt-1 font-editorial text-xl text-ink">Subscription</h2>

            @if($subscription)
                <div class="mt-4 flex items-center justify-between gap-2">
                    <span class="badge {{ $subscription->plan === 'deep_scan' ? 'badge-emerald' : 'badge-neutral' }}">
                        {{ str($subscription->plan)->replace('_', ' ')->title() }}
                    </span>
                    <span class="font-mono text-[10px] capitalize text-muted">{{ $subscription->status }}</span>
                </div>
                <dl class="mt-4 space-y-2.5 font-body text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Renews</dt>
                        <dd class="font-mono text-[10px] text-ink">{{ $subscription->current_period_end?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-muted">Provider</dt>
                        <dd class="capitalize text-ink">{{ $subscription->provider ?? '—' }}</dd>
                    </div>
                </dl>
            @else
                <p class="mt-4 font-body text-xs leading-relaxed text-muted">You are on the free Snapshot plan. Monitoring and deep reports require Deep Scan.</p>
                <a href="{{ route('home') }}#pricing" class="btn-secondary mt-4 w-full">Compare plans</a>
            @endif

            <div class="mt-5 rounded-xl border border-hairline bg-canvas-alt px-3 py-2.5">
                <p class="font-mono text-[9px] uppercase tracking-wider text-muted">Billing safety</p>
                <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">TraceLess never stores card numbers. Checkout is delegated to a PCI-compliant provider.</p>
            </div>
        </div>

        {{-- Sign-in method --}}
        <div class="glass-card p-6">
            <p class="mono-label">Security</p>
            <h2 class="mt-1 font-editorial text-xl text-ink">Sign-in method</h2>

            <div class="mt-4 rounded-xl border border-hairline bg-canvas-alt px-3 py-3">
                <p class="font-mono text-[9px] uppercase tracking-wider text-muted">Passwordless email OTP</p>
                <p class="mt-1.5 break-all font-body text-sm font-medium text-ink">{{ $user->email }}</p>
                <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">A 6-digit code is sent to this address each time you sign in. TraceLess stores no password.</p>
            </div>

            <ul class="mt-4 space-y-2 font-body text-[11px] leading-relaxed text-muted">
                <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] text-emerald">check</span> Single-use codes, hashed at rest</li>
                <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] text-emerald">check</span> Expires after {{ config('traceless.otp.expiry_minutes', 10) }} minutes</li>
                <li class="flex items-start gap-2"><span class="material-symbols-outlined text-[14px] text-emerald">check</span> Locks after {{ config('traceless.otp.max_attempts', 5) }} wrong attempts</li>
            </ul>

            <p class="mt-3 font-body text-[11px] leading-relaxed text-muted">To change your sign-in email, update it in Account profile and confirm the code sent to the new address.</p>
        </div>

        {{-- Data control --}}
        <div class="glass-card p-6">
            <p class="mono-label">Your data</p>
            <h2 class="mt-1 font-editorial text-xl text-ink">Export &amp; retention</h2>
            <p class="mt-2 font-body text-xs leading-relaxed text-muted">Download a machine-readable copy of your profile, findings, removals and audit trail. Exports are generated on demand and never shared.</p>
            <a href="{{ route('account.export') }}" class="btn-secondary mt-4 w-full">
                <span class="material-symbols-outlined text-[16px]">download</span> Export my data
            </a>
            <div class="mt-4 rounded-xl border border-hairline bg-canvas-alt px-3 py-2.5">
                <p class="font-mono text-[9px] uppercase tracking-wider text-muted">Retention default</p>
                <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">Completed scan artifacts expire after {{ config('traceless.retention.scan_telemetry_days', 365) }} days; reports after {{ config('traceless.retention.report_days', 730) }} days.</p>
            </div>
        </div>

        {{-- Audit --}}
        <div class="glass-card p-6 lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="mono-label">Audit trail</p>
                    <h2 class="mt-1 font-editorial text-xl text-ink">Recent account events</h2>
                </div>
                <span class="font-mono text-[10px] text-muted">Last 10</span>
            </div>
            <div class="mt-4 divide-y divide-hairline">
                @forelse($auditLogs as $log)
                    <div class="flex items-start justify-between gap-3 py-2.5">
                        <div>
                            <p class="font-mono text-[11px] text-charcoal">{{ str_replace('_', ' ', $log->action) }}</p>
                            <p class="mt-0.5 font-body text-[11px] text-muted">{{ $log->details }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-mono text-[9px] text-muted">{{ $log->created_at->format('d M Y') }}</p>
                            <p class="font-mono text-[9px] text-muted/70">{{ $log->ip_address ?? 'local' }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-4 font-mono text-xs text-muted">No audit events recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Danger zone --}}
    <div class="rounded-3xl border border-coral/25 bg-coral/[.035] p-6">
        <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-coral">Danger zone</p>
        <h2 class="mt-1 font-editorial text-2xl text-ink">Irreversible actions</h2>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <div class="rounded-2xl border border-hairline bg-white/70 p-5">
                <p class="font-body text-sm font-semibold text-ink">Purge scan data</p>
                <p class="mt-1 font-body text-xs leading-relaxed text-muted">Deletes all findings, scans, reports, credential records, removal tasks and monitoring preferences. Your account remains.</p>
                <label class="mt-3 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" wire:model="confirm_data_purge" class="mt-0.5 rounded border-hairline text-coral focus:ring-coral/30">
                    <span class="font-body text-[11px] leading-relaxed text-muted">I understand this cannot be undone.</span>
                </label>
                @error('confirm_data_purge')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
                <button wire:click="purgeScanData" wire:confirm="Permanently delete all scan findings and reports?" class="btn-danger mt-3 w-full">Purge scan data</button>
            </div>

            <div class="rounded-2xl border border-coral/30 bg-white/70 p-5">
                <p class="font-body text-sm font-semibold text-ink">Delete account</p>
                <p class="mt-1 font-body text-xs leading-relaxed text-muted">Permanently removes your account and all associated identity, scan and monitoring data.</p>

                @if(! $delete_code_sent)
                    <button wire:click="requestDeleteCode" class="btn-secondary mt-3 w-full">
                        <span class="material-symbols-outlined text-[16px]">mail</span> Send deletion code
                    </button>
                @else
                    @if($delete_demo_code)
                        <div class="mt-3 rounded-lg border border-amber/30 bg-amber/[.07] px-3 py-2">
                            <p class="font-mono text-[9px] uppercase tracking-wider text-[#9A6700]">Demo mode — code shown locally</p>
                            <p class="mt-0.5 font-mono text-xl font-semibold tracking-[0.3em] text-ink">{{ $delete_demo_code }}</p>
                        </div>
                    @else
                        <p class="mt-3 font-body text-[11px] leading-relaxed text-muted">A 6-digit code was sent to <span class="font-medium text-ink">{{ $user->email }}</span>.</p>
                    @endif

                    <div class="mt-3">
                        <label for="deleteCode" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-muted">Deletion code</label>
                        <input id="deleteCode" type="text" wire:model="delete_code" inputmode="numeric" maxlength="6" placeholder="000000"
                               class="input-base font-mono tracking-[0.3em]">
                        @error('delete_code')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
                    </div>
                @endif

                <label class="mt-3 flex cursor-pointer items-start gap-2">
                    <input type="checkbox" wire:model="confirm_account_delete" class="mt-0.5 rounded border-hairline text-coral focus:ring-coral/30">
                    <span class="font-body text-[11px] leading-relaxed text-muted">I understand my account will be permanently deleted.</span>
                </label>
                @error('confirm_account_delete')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
                <button wire:click="deleteAccount" wire:confirm="Delete your account and every stored record?" class="btn-danger mt-3 w-full" @disabled(! $delete_code_sent)>Delete account</button>
            </div>
        </div>
    </div>
</div>
