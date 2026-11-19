<div class="space-y-7">
    <x-page-header
        eyebrow="Administration"
        title="Failed jobs"
        description="Jobs that exhausted their retries. A failed scan job is a scan the user believes is still running.">
        <a href="{{ route('admin.overview') }}" class="btn-secondary text-xs">
            <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            <span>Overview</span>
        </a>
    </x-page-header>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Failed total</p>
            <p class="mt-1 font-display text-2xl font-semibold {{ $total > 0 ? 'text-danger' : 'text-ink' }}">{{ number_format($total) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Failed in 24h</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($last24h) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] uppercase tracking-wide text-faint">Pending jobs</p>
            <p class="mt-1 font-display text-2xl font-semibold text-ink">{{ number_format($pending) }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="inline-flex items-center gap-2 text-xs text-muted">
            <input type="checkbox" wire:model.live="onlyRecent" class="rounded border-line text-accent" />
            Only the last 24 hours
        </label>
        <button type="button" wire:click="retryAll" wire:confirm="Requeue every failed job?"
                class="btn-secondary !px-3 !py-2 !text-xs">Retry all</button>
    </div>

    <div class="card overflow-hidden">
        <div class="divide-y divide-line">
            @forelse($jobs as $job)
                <div class="px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-ink">{{ $job->payload_data['display'] }}</p>
                            <p class="mt-0.5 text-[11px] text-faint">
                                queue {{ $job->queue }} · {{ $job->payload_data['queue'] }} · failed {{ \Illuminate\Support\Carbon::parse($job->failed_at)->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" wire:click="retryJob('{{ $job->uuid }}')"
                                    class="btn-secondary !px-3 !py-1.5 !text-xs">Retry</button>
                            <button type="button" wire:click="forgetJob('{{ $job->uuid }}')"
                                    wire:confirm="Delete this failed job permanently?"
                                    class="btn-secondary !px-3 !py-1.5 !text-xs">Delete</button>
                        </div>
                    </div>
                    <details class="mt-2">
                        <summary class="cursor-pointer text-[11px] text-muted">Exception</summary>
                        <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-control border border-line bg-canvas p-3 font-mono text-[10px] leading-relaxed text-body">{{ $job->exception_excerpt }}</pre>
                    </details>
                </div>
            @empty
                <p class="px-5 py-10 text-center text-sm text-muted">No failed jobs. The queue is clean.</p>
            @endforelse
        </div>
        @if($jobs->hasPages())
            <div class="border-t border-line px-5 py-3">{{ $jobs->links() }}</div>
        @endif
    </div>
</div>
