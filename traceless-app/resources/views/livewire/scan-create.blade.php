<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('scans.index') }}" class="btn-ghost !px-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span> Scans</a>
        <span class="badge badge-neutral"><span class="material-symbols-outlined text-[12px]">public</span> Public sources only</span>
    </div>

    <div class="glass-card overflow-hidden">
        <div class="relative border-b border-hairline bg-elevated px-6 py-6">
            <div class="pointer-events-none absolute -right-14 -top-16 h-52 w-52 rounded-full bg-electric-blue/10 blur-3xl"></div>
            <p class="eyebrow">New assessment</p>
            <h1 class="display-title mt-1 text-[2rem] text-ink">Start a privacy scan</h1>
            <p class="mt-2 max-w-xl font-body text-sm leading-relaxed text-muted">Choose an identity and scan depth. Consent is re-checked before the queue accepts the job.</p>
        </div>

        <form wire:submit="start" class="space-y-6 px-6 py-6">
            @if($profiles->isEmpty())
                <x-empty-state icon="fingerprint" title="No identity configured" description="Create an identity profile before starting a scan.">
                    <a href="{{ route('identity.create') }}" class="btn-primary">Create identity</a>
                </x-empty-state>
            @else
                <div>
                    <label for="profile" class="mono-label mb-1.5 block">Identity profile</label>
                    <select id="profile" wire:model="profile_id" class="input-base">
                        @foreach($profiles as $profile)
                            <option value="{{ $profile->id }}">{{ $profile->full_name }} · {{ $profile->primary_email }}</option>
                        @endforeach
                    </select>
                    @error('profile_id') <p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p> @enderror
                </div>

                <div>
                    <p class="mono-label mb-2">Scan depth</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-2xl border border-hairline bg-elevated p-4 transition has-[:checked]:border-electric-blue has-[:checked]:bg-electric-blue/[.05]">
                            <input type="radio" wire:model.live="type" value="snapshot" class="sr-only">
                            <div class="flex items-start justify-between">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-electric-blue/10 text-electric-blue"><span class="material-symbols-outlined text-[18px]">bolt</span></span>
                                <span class="badge badge-neutral">Included</span>
                            </div>
                            <p class="mt-3 font-body text-sm font-semibold">Privacy Snapshot</p>
                            <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">One identity, public profile discovery, basic credential metadata and removal guidance.</p>
                        </label>

                        <label class="cursor-pointer rounded-2xl border border-hairline bg-elevated p-4 transition has-[:checked]:border-violet has-[:checked]:bg-violet/[.05]">
                            <input type="radio" wire:model.live="type" value="deep_scan" class="sr-only">
                            <div class="flex items-start justify-between">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet/10 text-violet"><span class="material-symbols-outlined text-[18px]">neurology</span></span>
                                <span class="badge {{ $isDeepScan ? 'badge-emerald' : 'badge-moderate' }}">{{ $isDeepScan ? 'Active' : 'Deep Scan' }}</span>
                            </div>
                            <p class="mt-3 font-body text-sm font-semibold">Deep Scan</p>
                            <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">Expanded sources, recurring monitoring, reappearance alerts and advanced reports.</p>
                        </label>
                    </div>
                    @error('type') <p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p> @enderror
                </div>

                @if($type === 'deep_scan')
                    <div class="rounded-2xl border border-violet/20 bg-violet/[.05] p-4">
                        <label class="flex items-center gap-2 font-mono text-[11px] text-charcoal">
                            <input type="checkbox" wire:model="recurring" class="rounded border-hairline text-violet focus:ring-violet">
                            Enable recurring monitoring after this scan
                        </label>
                        @if($recurring)
                            <div class="mt-3">
                                <label class="mono-label mb-1.5 block">Cadence</label>
                                <select wire:model="cadence" class="select-base w-full sm:w-auto">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                        @endif
                        @if(!$isDeepScan && $demoMode)
                            <div class="mt-3 flex flex-col gap-2 border-t border-violet/15 pt-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="font-mono text-[9px] text-muted">Demo mode: enable Deep Scan without payment to test recurring monitoring.</p>
                                <button type="button" wire:click="enableDemoDeepScan" class="btn-secondary !px-3 !py-2 !text-[10px] shrink-0">Enable demo Deep Scan</button>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="rounded-2xl border border-amber/25 bg-amber/[.06] p-4">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" wire:model="consent_confirmed" class="mt-0.5 rounded border-hairline text-electric-blue focus:ring-electric-blue">
                        <span class="font-body text-xs leading-relaxed text-charcoal">
                            <span class="font-semibold text-ink">I confirm I own this information or have permission to scan it.</span><br>
                            I understand TraceLess checks public sources only, never requests passwords, and that removal cannot be guaranteed for every source.
                        </span>
                    </label>
                    @error('consent_confirmed') <p class="mt-2 font-mono text-[10px] text-coral">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" class="btn-primary w-full">
                    <span wire:loading.remove wire:target="start">Queue public-source scan</span>
                    <span wire:loading wire:target="start">Queueing scan…</span>
                </button>
                <p class="text-center font-mono text-[9px] leading-relaxed text-muted">Jobs run in the background. You will see live progress on the scan page and receive an alert when complete.</p>
            @endif
        </form>
    </div>
</div>
