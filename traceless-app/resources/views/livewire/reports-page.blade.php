<div class="space-y-6">
    <x-page-header eyebrow="Evidence &amp; exports" title="Reports" description="Generate a signed privacy dossier you can keep, print or share with a lawyer, employer or support service.">
        @if($profile)
            <button wire:click="generate" wire:loading.attr="disabled" class="btn-primary">
                <span class="material-symbols-outlined text-[16px]" wire:loading.remove>add</span>
                <span class="material-symbols-outlined animate-spin text-[16px]" wire:loading>progress_activity</span>
                Generate {{ $type === 'deep_scan' ? 'deep report' : 'snapshot' }}
            </button>
        @endif
    </x-page-header>

    @if(!$profile)
        <x-empty-state icon="description" title="No identity profile yet" description="Reports are built from the findings, credential exposures and removal records attached to an identity you own.">
            <a href="{{ route('identity.create') }}" class="btn-primary">Create identity</a>
        </x-empty-state>
    @else
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="glass-card p-5 lg:col-span-2">
                <p class="mono-label">Report type</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <button type="button" wire:click="$set('type', 'snapshot')"
                            class="rounded-2xl border p-4 text-left transition {{ $type === 'snapshot' ? 'border-electric-blue/40 bg-electric-blue/[.06] shadow-glass-sm' : 'border-hairline bg-elevated hover:bg-elevated' }}">
                        <span class="flex items-center justify-between">
                            <span class="font-body text-sm font-medium text-ink">Snapshot report</span>
                            <span class="material-symbols-outlined text-[17px] {{ $type === 'snapshot' ? 'text-electric-blue' : 'text-muted' }}">photo_camera</span>
                        </span>
                        <span class="mt-1 block font-body text-[11px] leading-snug text-muted">Current score, all active findings and removal progress.</span>
                    </button>
                    <button type="button" wire:click="$set('type', 'deep_scan')"
                            class="rounded-2xl border p-4 text-left transition {{ $type === 'deep_scan' ? 'border-electric-blue/40 bg-electric-blue/[.06] shadow-glass-sm' : 'border-hairline bg-elevated hover:bg-elevated' }} {{ $isDeepScan ? '' : 'opacity-70' }}">
                        <span class="flex items-center justify-between">
                            <span class="font-body text-sm font-medium text-ink">Deep report</span>
                            <span class="material-symbols-outlined text-[17px] {{ $type === 'deep_scan' ? 'text-electric-blue' : 'text-muted' }}">travel_explore</span>
                        </span>
                        <span class="mt-1 block font-body text-[11px] leading-snug text-muted">Extended narrative for Deep Scan subscribers.</span>
                        @unless($isDeepScan)
                            <span class="mt-2 inline-flex items-center gap-1 font-mono text-[9px] text-muted"><span class="material-symbols-outlined text-[12px]">lock</span>Free during v1.0 beta</span>
                        @endunless
                    </button>
                </div>
                @error('type')<p class="mt-2 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
                <p class="mt-3 font-body text-[11px] leading-relaxed text-muted">Reports are signed HTML dossiers. Open in any browser and use “Print → Save as PDF” for a permanent copy. Links expire and every download is logged.</p>
            </div>

            <div class="glass-card flex flex-col items-center justify-center p-5">
                <x-score-ring :score="$profile->privacy_score" :size="150" />
                <p class="mt-3 text-center font-mono text-[10px] leading-relaxed text-muted">{{ $profile->full_name }}<br>Score recalculates after every scan and removal.</p>
            </div>
        </div>

        <div class="glass-card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-hairline px-5 py-4">
                <div>
                    <p class="mono-label">Dossier archive</p>
                    <h2 class="mt-0.5 font-editorial text-xl text-ink">Generated reports</h2>
                </div>
                <span class="font-mono text-[10px] text-muted">{{ $reports->count() }} total</span>
            </div>

            <div class="divide-y divide-hairline">
                @forelse($reports as $report)
                    @php($summary = json_decode($report->summary ?? '{}', true) ?: [])
                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-hairline bg-elevated text-electric-blue">
                                <span class="material-symbols-outlined text-[17px]">description</span>
                            </span>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-body text-sm font-medium text-ink">{{ $report->title }}</p>
                                    <span class="badge {{ $report->report_type === 'deep_scan' ? 'badge-low' : 'badge-neutral' }}">{{ str($report->report_type)->replace('_', ' ')->title() }}</span>
                                    @if($report->demo_mode)<span class="badge badge-moderate">Demo data</span>@endif
                                </div>
                                <p class="mt-1 font-mono text-[10px] text-muted">
                                    Score {{ $report->privacy_score }}/100 · {{ $report->total_findings }} findings · {{ $report->generated_at?->format('d M Y, H:i') }} · downloaded {{ $report->download_count }}×
                                </p>
                                @if($report->signature)
                                    <p class="mt-0.5 truncate font-mono text-[9px] text-muted/80">sig {{ Str::limit($report->signature, 32) }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <a href="{{ URL::temporarySignedRoute('reports.download', now()->addMinutes(30), ['report' => $report->id, 'token' => $report->download_token]) }}"
                               class="btn-secondary !px-3 !py-2 !text-[10px]">
                                <span class="material-symbols-outlined text-[14px]">download</span> Download
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <span class="material-symbols-outlined text-3xl text-muted">description</span>
                        <p class="mt-2 font-mono text-xs text-muted">No reports generated yet. Create a snapshot to export your current posture.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="flex items-start gap-2 rounded-xl border border-hairline bg-elevated px-4 py-3">
            <span class="material-symbols-outlined text-[16px] text-muted">verified_user</span>
            <p class="font-body text-[11px] leading-relaxed text-muted">Reports contain public-source findings only. TraceLess never includes passwords or private credentials. Removal outcomes are not guaranteed and scores are estimates.</p>
        </div>
    @endif
</div>
