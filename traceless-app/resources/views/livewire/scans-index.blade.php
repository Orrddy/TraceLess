<div class="space-y-6">
    <x-page-header eyebrow="Scan telemetry" title="Scan history" description="Every public-source scan, its stage telemetry and its signed report trail.">
        <a href="{{ route('scans.create') }}" class="btn-primary"><span class="material-symbols-outlined text-[16px]">add</span> New scan</a>
    </x-page-header>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <x-stat label="Total scans" :value="$stats['total']" icon="history" tone="ink" />
        <x-stat label="Active now" :value="$stats['active']" icon="radar" tone="blue" hint="Queued or running" />
        <x-stat label="Completed" :value="$stats['completed']" icon="check_circle" tone="emerald" />
    </div>

    <div class="glass-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-hairline px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="mono-label">Scan ledger</p>
            <select wire:model.live="status" class="select-base">
                <option value="">All statuses</option>
                @foreach(['queued','checking_sources','normalizing_results','calculating_risk','generating_report','completed','failed','cancelled'] as $status)
                    <option value="{{ $status }}">{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </div>

        <div class="divide-y divide-hairline">
            @forelse($scans as $scan)
                <div class="flex flex-col gap-3 px-5 py-4 transition hover:bg-white/40 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-hairline bg-white">
                            <span class="material-symbols-outlined text-[17px] {{ $scan->status === 'completed' ? 'text-emerald' : ($scan->status === 'failed' ? 'text-coral' : 'text-electric-blue') }}">
                                {{ $scan->status === 'completed' ? 'check_circle' : ($scan->status === 'failed' ? 'error' : 'radar') }}
                            </span>
                        </span>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('scans.show', $scan) }}" class="font-body text-sm font-medium text-ink hover:text-electric-blue">Scan #{{ $scan->id }}</a>
                                <x-status-badge :status="$scan->status" />
                                @if($scan->demo_mode)<span class="badge badge-neutral">Demo</span>@endif
                            </div>
                            <p class="mt-0.5 font-mono text-[10px] text-muted">
                                {{ ucfirst(str_replace('_', ' ', $scan->type)) }} · {{ $scan->identityProfile?->full_name }} · {{ $scan->findings_count }} findings · {{ $scan->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 sm:shrink-0">
                        @if($scan->isActive())
                            <button wire:click="cancel({{ $scan->id }})" wire:confirm="Cancel this scan?" class="btn-secondary !px-3 !py-2 !text-[10px] text-coral">Cancel</button>
                        @endif
                        <a href="{{ route('scans.show', $scan) }}" class="btn-secondary !px-3 !py-2 !text-[10px]">Inspect →</a>
                    </div>
                </div>
            @empty
                <div class="px-5 py-12 text-center">
                    <span class="material-symbols-outlined text-3xl text-muted">radar</span>
                    <p class="mt-2 font-mono text-xs text-muted">No scans yet. Start your first privacy scan.</p>
                    <a href="{{ route('scans.create') }}" class="btn-primary mt-4">Start a scan</a>
                </div>
            @endforelse
        </div>
        @if($scans->hasPages())
            <div class="border-t border-hairline px-5 py-3">{{ $scans->links() }}</div>
        @endif
    </div>
</div>
