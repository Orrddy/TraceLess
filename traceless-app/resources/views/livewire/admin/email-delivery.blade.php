<div class="space-y-7">
    <x-page-header
        eyebrow="Administration"
        title="Email delivery"
        description="Passwordless sign-in depends on this channel. Every outbound message is recorded here.">
        <a href="{{ route('admin.overview') }}" class="btn-secondary text-xs">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            <span>Overview</span>
        </a>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-4">
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Sent (24h)</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($sent24h) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Failed (24h)</p>
            <p class="mt-1 font-display text-2xl font-semibold {{ $failed24h > 0 ? 'text-danger' : 'text-ink' }}">{{ number_format($failed24h) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Failed total</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($failedTotal) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Last success</p>
            <p class="mt-1 font-display text-lg font-semibold text-ink">{{ $lastSuccess?->diffForHumans() ?? 'never' }}</p>
        </div>
    </div>

    <div class="card p-5">
        <label for="status" class="label mb-1.5">Status</label>
        <select id="status" wire:model.live="statusFilter" class="input sm:w-56">
            <option value="">All messages</option>
            <option value="sent">Sent</option>
            <option value="failed">Failed</option>
        </select>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th class="px-5 py-3 text-left">When</th>
                        <th class="px-5 py-3 text-left">To</th>
                        <th class="px-5 py-3 text-left">Subject</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-left">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emails as $email)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3 text-[11px] text-faint">{{ $email->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-5 py-3 text-ink">
                                {{ $email->to_email }}
                                @if($email->user)<span class="block text-[11px] text-faint">{{ $email->user->name }}</span>@endif
                            </td>
                            <td class="max-w-[18rem] truncate px-5 py-3 text-muted">{{ $email->subject ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="badge {{ $email->status === 'failed' ? 'badge-critical' : 'badge-emerald' }}">{{ $email->status }}</span>
                            </td>
                            <td class="max-w-[20rem] px-5 py-3 text-[11px] leading-relaxed text-muted">
                                @if($email->error)
                                    <details>
                                        <summary class="cursor-pointer text-danger">{{ \Illuminate\Support\Str::limit($email->error, 90) }}</summary>
                                        <p class="mt-1 break-words">{{ $email->error }}</p>
                                    </details>
                                @else
                                    {{ $email->provider_message_id ?? '—' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-muted">No outbound email has been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($emails->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $emails->links() }}</div>
        @endif
    </div>
</div>
