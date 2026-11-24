<div class="space-y-7">
    <x-page-header
        eyebrow="Administration · scan"
        title="{{ $scan->reference() }}"
        description="{{ $scan->identityProfile?->full_name ?? 'Deleted profile' }} · {{ $scan->identityProfile?->user?->email ?? 'no account' }}">
        <a href="{{ route('admin.overview') }}" class="btn-secondary text-xs">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            <span>Overview</span>
        </a>
    </x-page-header>

    @if($errors->any())
        <div class="rounded-control border border-danger/30 bg-danger/[0.07] p-4 text-sm text-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        @foreach([
            ['Status', $scan->status, $scan->status === 'failed' ? 'text-danger' : 'text-ink'],
            ['Type', str($scan->type)->replace('_', ' '), 'text-ink'],
            ['Progress', $scan->progress_percentage.'%', 'text-ink'],
            ['Sources checked', $scan->sources_checked, 'text-ink'],
            ['Findings', $scan->findings_count, 'text-ink'],
            ['Started', $scan->started_at?->diffForHumans() ?? '—', 'text-ink'],
        ] as [$label, $value, $tone])
            <div class="card p-4">
                <p class="text-[11px] uppercase tracking-wide text-faint">{{ $label }}</p>
                <p class="mt-1 truncate font-display text-lg font-semibold {{ $tone }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @if($scan->error_message)
        <div class="rounded-control border border-danger/30 bg-danger/[0.07] p-4">
            <p class="text-sm font-semibold text-danger">Failure reason</p>
            <p class="mt-1 text-sm leading-relaxed text-body">{{ $scan->error_message }}</p>
        </div>
    @endif

    <div class="card flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs leading-relaxed text-muted">
            Stage: <span class="text-ink">{{ $scan->current_stage }}</span>
            @if($scan->completed_at) · finished {{ $scan->completed_at->diffForHumans() }} @endif
        </p>
        <div class="flex gap-2">
            <button type="button" wire:click="retry" wire:confirm="Queue a new {{ $scan->type }} scan for this profile?"
                    class="btn-secondary !px-3 !py-2 !text-xs">Retry scan</button>
            @if($scan->isActive())
                <button type="button" wire:click="cancel" wire:confirm="Cancel this running scan?"
                        class="btn-secondary !px-3 !py-2 !text-xs">Cancel scan</button>
            @endif
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-3">
            <h2 class="text-sm font-semibold text-ink">Sources</h2>
            <div class="flex flex-wrap gap-2 text-[11px]">
                @forelse($outcomeCounts as $outcome => $count)
                    <span class="badge {{ $outcome === 'found' ? 'badge-critical' : ($outcome === 'unknown' ? 'badge-amber' : 'badge-low') }}">
                        {{ $outcome ?? 'pending' }}: {{ $count }}
                    </span>
                @empty
                    <span class="text-muted">No source telemetry recorded.</span>
                @endforelse
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th class="px-5 py-2 text-left">Source</th>
                        <th class="px-5 py-2 text-left">Outcome</th>
                        <th class="px-5 py-2 text-left">HTTP</th>
                        <th class="px-5 py-2 text-left">Reason</th>
                        <th class="px-5 py-2 text-left">Checked URL</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sources as $source)
                        <tr>
                            <td class="px-5 py-2 text-ink">{{ $source->name }}<span class="block text-[11px] text-faint">{{ $source->category }}</span></td>
                            <td class="px-5 py-2">
                                <span class="badge {{ in_array($source->outcome, ['found'], true) ? 'badge-critical' : ($source->outcome === 'unknown' ? 'badge-amber' : 'badge-low') }}">
                                    {{ $source->outcome ?? $source->status }}
                                </span>
                            </td>
                            <td class="px-5 py-2 font-mono text-muted">{{ $source->http_status ?? '—' }}</td>
                            <td class="max-w-[16rem] truncate px-5 py-2 text-[11px] text-muted">{{ $source->outcome_reason ?? '—' }}</td>
                            <td class="max-w-[22rem] truncate px-5 py-2 font-mono text-[11px] text-faint">{{ $source->checked_url ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-6 text-center text-sm text-muted">No sources were recorded for this scan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="border-b border-line px-5 py-3">
            <h2 class="text-sm font-semibold text-ink">Findings produced</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th class="px-5 py-2 text-left">Source</th>
                        <th class="px-5 py-2 text-left">Title</th>
                        <th class="px-5 py-2 text-left">Verification</th>
                        <th class="px-5 py-2 text-left">Risk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($findings as $finding)
                        <tr>
                            <td class="px-5 py-2 text-ink">{{ $finding->source }}</td>
                            <td class="px-5 py-2 text-muted">{{ \Illuminate\Support\Str::limit($finding->title, 70) }}</td>
                            <td class="px-5 py-2">
                                <span class="badge {{ $finding->verification_status === 'verified' ? 'badge-emerald' : 'badge-amber' }}">
                                    {{ $finding->verification_status }}
                                </span>
                            </td>
                            <td class="px-5 py-2 text-muted">{{ $finding->risk }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-6 text-center text-sm text-muted">This scan produced no findings.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
