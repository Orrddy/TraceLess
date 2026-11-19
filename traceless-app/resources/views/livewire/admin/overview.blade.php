<div class="space-y-7">
    <x-page-header
        eyebrow="Administration"
        title="Overview"
        description="Live operational counts. Every figure below comes straight from the database.">
        <a href="{{ route('admin.activity') }}" class="btn-secondary text-xs">
            <span class="material-symbols-outlined text-[16px]">history</span>
            <span>Activity log</span>
        </a>
    </x-page-header>

    {{-- Headline counts --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach([
            ['Users', $stats['users_total'], $stats['users_new'].' new this week'],
            ['Profiles', $stats['profiles_total'], $stats['admins_total'].' admin accounts'],
            ['Scans', $stats['scans_total'], $stats['scans_today'].' today'],
            ['Findings', $stats['findings_total'], $stats['findings_new'].' new this week'],
            ['Removals', $stats['removals_total'], $stats['removals_done'].' completed'],
            ['Unread alerts', $stats['alerts_unread'], $stats['subs_active'].' active plans'],
        ] as [$label, $value, $note])
            <div class="card p-4">
                <p class="text-[11px] uppercase tracking-wide text-faint">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($value) }}</p>
                <p class="mt-1 text-[11px] leading-snug text-muted">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    {{-- Scan pipeline + queue --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-ink">Scan pipeline</h2>
                <span class="badge badge-low">{{ $stats['scans_active'] }} active</span>
            </div>
            <dl class="mt-3 space-y-2 text-sm">
                @forelse($scanStatuses as $status => $count)
                    <div class="flex items-center justify-between">
                        <dt class="text-muted">{{ str($status)->replace('_', ' ')->title() }}</dt>
                        <dd class="font-mono text-ink">{{ number_format($count) }}</dd>
                    </div>
                @empty
                    <p class="text-sm text-muted">No scans have been run yet.</p>
                @endforelse
                <div class="flex items-center justify-between border-t border-line pt-2">
                    <dt class="text-muted">Failed</dt>
                    <dd class="font-mono {{ $stats['scans_failed'] > 0 ? 'text-danger' : 'text-ink' }}">{{ number_format($stats['scans_failed']) }}</dd>
                </div>
            </dl>
        </div>

        <div class="card p-5">
            <h2 class="text-sm font-semibold text-ink">Queue</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Pending jobs</dt>
                    <dd class="font-mono text-ink">{{ number_format($stats['queue_pending']) }}</dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">Failed jobs</dt>
                    <dd class="font-mono {{ $stats['queue_failed'] > 0 ? 'text-danger' : 'text-ink' }}">{{ number_format($stats['queue_failed']) }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-[11px] leading-relaxed text-faint">
                Reads the jobs and failed_jobs tables directly. A growing failed count means scans are being dropped.
            </p>
        </div>

        <div class="card p-5">
            <h2 class="text-sm font-semibold text-ink">Newest accounts</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse($recentUsers as $user)
                    <li class="flex items-center justify-between gap-2">
                        <span class="min-w-0">
                            <span class="block truncate text-ink">{{ $user->name }}</span>
                            <span class="block truncate text-[11px] text-faint">{{ $user->email }}</span>
                        </span>
                        <span class="badge badge-low shrink-0">{{ $user->roleLabel() }}</span>
                    </li>
                @empty
                    <li class="text-sm text-muted">No accounts yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Recent scans --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-5 py-3">
            <h2 class="text-sm font-semibold text-ink">Recent scans</h2>
            <a href="{{ route('admin.users') }}" class="text-xs font-medium text-accent hover:underline">Manage users</a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th class="px-5 py-2 text-left">#</th>
                        <th class="px-5 py-2 text-left">Profile</th>
                        <th class="px-5 py-2 text-left">Type</th>
                        <th class="px-5 py-2 text-left">Status</th>
                        <th class="px-5 py-2 text-left">Findings</th>
                        <th class="px-5 py-2 text-left">Started</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentScans as $scan)
                        <tr>
                            <td class="px-5 py-2 font-mono text-faint">{{ $scan->id }}</td>
                            <td class="px-5 py-2 text-ink">
                                {{ $scan->identityProfile?->full_name ?? 'Deleted profile' }}
                                <span class="block text-[11px] text-faint">{{ $scan->identityProfile?->user?->email }}</span>
                            </td>
                            <td class="px-5 py-2 text-muted">{{ str($scan->type)->replace('_', ' ') }}</td>
                            <td class="px-5 py-2">
                                <span class="badge {{ $scan->status === 'failed' ? 'badge-critical' : ($scan->status === 'completed' ? 'badge-emerald' : 'badge-low') }}">
                                    {{ $scan->status }}
                                </span>
                            </td>
                            <td class="px-5 py-2 font-mono text-muted">{{ $scan->findings_count }}</td>
                            <td class="px-5 py-2 text-[11px] text-faint">{{ $scan->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-6 text-center text-sm text-muted">No scans recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-line px-5 py-3">
            <h2 class="text-sm font-semibold text-ink">Recent activity</h2>
            <a href="{{ route('admin.activity') }}" class="text-xs font-medium text-accent hover:underline">Open the full log</a>
        </div>
        <ul class="divide-y divide-line">
            @forelse($recentAudit as $entry)
                <li class="flex items-start justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm text-ink">{{ $entry->action }}</p>
                        <p class="truncate text-[11px] text-faint">
                            {{ $entry->user?->email ?? 'system' }} · {{ $entry->ip_address ?? 'no ip' }}
                        </p>
                    </div>
                    <span class="shrink-0 text-[11px] text-faint">{{ $entry->created_at?->diffForHumans() }}</span>
                </li>
            @empty
                <li class="px-5 py-6 text-center text-sm text-muted">No audit entries yet.</li>
            @endforelse
        </ul>
    </div>
</div>
