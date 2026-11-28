<div class="space-y-6">
    <x-page-header
        eyebrow="Administration"
        title="Quick scans"
        description="Guest quick-scan records, retained for admin review. Green sources matched either the username or the email.">
        <a href="{{ route('admin.overview') }}" class="btn-secondary text-xs">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            <span>Overview</span>
        </a>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">All quick scans</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($total) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Today</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($today) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">With green sources</p>
            <p class="mt-1 font-display text-2xl font-semibold text-success">{{ number_format($withGreen) }}</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="border-b border-line px-4 py-3 sm:px-6">
            <h2 class="text-sm font-semibold text-ink">Guest checks</h2>
            <p class="mt-1 text-[11px] text-muted">Name and email are collected only for this admin audit trail and signup prefill.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[980px] w-full text-left text-sm">
                <thead class="border-b border-line bg-elevated/40 text-[10px] uppercase tracking-[0.13em] text-faint">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Username</th>
                        <th class="px-4 py-3 font-medium">Green sources</th>
                        <th class="px-4 py-3 font-medium">Matches</th>
                        <th class="px-4 py-3 font-medium">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line/60">
                    @forelse($records as $record)
                        <tr class="align-top">
                            <td class="px-4 py-3 font-medium text-ink">{{ $record->name }}</td>
                            <td class="px-4 py-3 text-muted">{{ $record->email }}</td>
                            <td class="px-4 py-3 font-mono text-ink"><span>@</span>{{ $record->username }}</td>
                            <td class="px-4 py-3">
                                <div class="flex max-w-[360px] flex-wrap gap-1">
                                    @forelse($record->green_sources as $source)
                                        <span class="badge badge-low">{{ $source }}</span>
                                    @empty
                                        <span class="text-faint">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px] text-muted">
                                {{ $record->username_matches }} username · {{ $record->email_matches }} email
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-[11px] text-faint">
                                {{ $record->created_at?->format('j M Y, H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-muted">No guest quick scans yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $records->links() }}</div>
        @endif
    </div>
</div>
