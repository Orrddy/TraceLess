<div class="space-y-7">
    <x-page-header
        eyebrow="Administration"
        title="Activity log"
        description="Every security-relevant event the application records, including actions taken from this panel.">
        <a href="{{ route('admin.overview') }}" class="btn-secondary text-xs">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            <span>Overview</span>
        </a>
    </x-page-header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Entries shown</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($logs->total()) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Today</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($todayCount) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Last 7 days</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($weekCount) }}</p>
        </div>
    </div>

    <div class="card p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="log-search" class="label mb-1.5">Search</label>
                <input id="log-search" type="search" wire:model.live.debounce.400ms="search" class="input"
                       placeholder="Action, detail, email or IP" />
            </div>
            <div>
                <label for="log-action" class="label mb-1.5">Action</label>
                <select id="log-action" wire:model.live="actionFilter" class="input">
                    <option value="">All actions</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="log-from" class="label mb-1.5">From</label>
                <input id="log-from" type="date" wire:model.live="dateFrom" class="input" />
            </div>
            <div>
                <label for="log-to" class="label mb-1.5">To</label>
                <input id="log-to" type="date" wire:model.live="dateTo" class="input" />
            </div>
        </div>
        <button type="button" wire:click="clearFilters" class="btn-secondary mt-3 !px-3 !py-1.5 !text-xs">
            Clear filters
        </button>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th class="px-5 py-3 text-left">When</th>
                        <th class="px-5 py-3 text-left">Action</th>
                        <th class="px-5 py-3 text-left">Account</th>
                        <th class="px-5 py-3 text-left">IP</th>
                        <th class="px-5 py-3 text-left">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $entry)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3 text-[11px] text-faint">
                                {{ $entry->created_at?->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="font-mono text-[11px] {{ str_starts_with($entry->action, 'admin.') ? 'text-warning' : 'text-ink' }}">
                                    {{ $entry->action }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                @if($entry->user)
                                    <span class="block text-ink">{{ $entry->user->name }}</span>
                                    <span class="block text-[11px] text-faint">{{ $entry->user->email }}</span>
                                @else
                                    <span class="text-[11px] text-faint">system / deleted account</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 font-mono text-[11px] text-muted">{{ $entry->ip_address ?? '—' }}</td>
                            <td class="max-w-md px-5 py-3 text-[11px] leading-relaxed text-muted">
                                @if($entry->details)
                                    <details>
                                        <summary class="cursor-pointer text-ink/80">{{ str($entry->details)->limit(90) }}</summary>
                                        <p class="mt-1 whitespace-pre-wrap break-words">{{ $entry->details }}</p>
                                    </details>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-muted">No audit entries match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $logs->links() }}</div>
        @endif
    </div>

    <p class="text-[11px] leading-relaxed text-faint">
        Read-only. Entries are written by the application itself; the log stores no scan targets, and
        emails/numbers in free-text details are redacted before they are saved.
    </p>
</div>
