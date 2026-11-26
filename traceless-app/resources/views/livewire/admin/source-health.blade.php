<div class="space-y-7">
    <x-page-header
        eyebrow="Administration"
        title="Source health"
        description="Which public sources actually answer. Unknown and blocked checks are not evidence of absence.">
        <a href="{{ route('admin.overview') }}" class="btn-secondary text-xs">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            <span>Overview</span>
        </a>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Sources observed</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($sourceCount) }}</p>
            <p class="mt-1 text-[11px] text-muted">last {{ $days }} days</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Checks recorded</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($totalChecks) }}</p>
            <p class="mt-1 text-[11px] text-muted">{{ number_format($healthyRate, 1) }}% returned a verdict</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Unknown outcomes</p>
            <p class="mt-1 font-display text-2xl font-semibold {{ $unknownRate > 20 ? 'text-warning' : 'text-ink' }}">{{ number_format($unknownRate, 1) }}%</p>
            <p class="mt-1 text-[11px] text-muted">timeout, rule mismatch or error</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Blocked responses</p>
            <p class="mt-1 font-display text-2xl font-semibold {{ $blockedRate > 5 ? 'text-danger' : 'text-ink' }}">{{ number_format($blockedRate, 1) }}%</p>
            <p class="mt-1 text-[11px] text-muted">401 / 403 / 429 / 451 / 999</p>
        </div>
    </div>

    <div class="rounded-xl border border-amber/25 bg-amber/[0.06] px-4 py-3 text-[11px] leading-relaxed text-body">
        <span class="font-semibold text-ink">Read this page like an SRE dashboard, not a leaderboard.</span>
        A 0% find rate can be a genuinely clean identifier. A rising unknown or blocked rate means
        coverage is degrading — the scan should say “unverified”, never “clean”. Investigate before
        treating a source as healthy.
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex gap-1 rounded-xl border border-line bg-elevated/40 p-1">
            @foreach([7, 30, 90] as $window)
                <button type="button" wire:click="setDays({{ $window }})"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $days === $window ? 'bg-ink text-canvas' : 'text-body hover:text-ink' }}">
                    {{ $window }}d
                </button>
            @endforeach
        </div>
        <div class="flex-1 sm:max-w-xs">
            <input type="search" wire:model.live.debounce.300ms="search" class="input !py-2 !text-xs"
                   placeholder="Filter source name" aria-label="Filter source name">
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th class="px-5 py-3 text-left">Source</th>
                        <th class="px-5 py-3 text-left">Category</th>
                        <th class="px-5 py-3 text-right">Checks</th>
                        <th class="px-5 py-3 text-right">Found</th>
                        <th class="px-5 py-3 text-right">Not found</th>
                        <th class="px-5 py-3 text-right">Unknown</th>
                        <th class="px-5 py-3 text-right">Blocked</th>
                        <th class="px-5 py-3 text-right">Verdict rate</th>
                        <th class="px-5 py-3 text-right">Last seen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $total = (int) $row->total;
                            $unknown = (int) $row->unknown_count;
                            $blocked = (int) $row->blocked_count;
                            $verdictRate = $total > 0 ? (($total - $unknown) / $total) * 100 : 100;
                            $rough = $unknown / max(1, $total) > 0.2 || $blocked > 0;
                            $lastSeen = $row->last_seen ? \Illuminate\Support\Carbon::parse($row->last_seen) : null;
                        @endphp
                        <tr wire:key="source-health-{{ $row->name }}">
                            <td class="px-5 py-3 font-medium text-ink">{{ $row->name }}</td>
                            <td class="px-5 py-3 text-[11px] uppercase tracking-wide text-muted">{{ str($row->category ?? 'unknown')->replace('_', ' ') }}</td>
                            <td class="px-5 py-3 text-right font-mono text-muted">{{ number_format($total) }}</td>
                            <td class="px-5 py-3 text-right font-mono {{ (int) $row->found_count > 0 ? 'text-success' : 'text-faint' }}">{{ number_format((int) $row->found_count) }}</td>
                            <td class="px-5 py-3 text-right font-mono text-muted">{{ number_format((int) $row->not_found_count) }}</td>
                            <td class="px-5 py-3 text-right font-mono {{ $unknown > 0 ? 'text-warning' : 'text-faint' }}">{{ number_format($unknown) }}</td>
                            <td class="px-5 py-3 text-right font-mono {{ $blocked > 0 ? 'text-danger' : 'text-faint' }}">{{ number_format($blocked) }}</td>
                            <td class="px-5 py-3 text-right">
                                <span class="badge {{ $verdictRate >= 90 ? 'badge-emerald' : ($verdictRate >= 70 ? 'badge-amber' : 'badge-critical') }}">
                                    {{ number_format($verdictRate, 1) }}%
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right text-[11px] {{ $rough ? 'text-warning' : 'text-muted' }}">
                                {{ $lastSeen?->diffForHumans(short: true) ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-10 text-center text-sm text-muted">
                                No source checks in this window yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-[11px] leading-relaxed text-faint">
        Fed by <code class="font-mono">scan_sources</code>. A source that is skipped or blocked during a scan must
        surface as unverified in the report; this panel exists to catch coverage regressions before a customer does.
    </p>
</div>
