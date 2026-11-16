<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" wire:poll.20s>
    <button @click="open = !open"
            class="relative flex h-9 w-9 items-center justify-center rounded-lg border border-line bg-elevated text-body transition hover:bg-surface"
            :aria-expanded="open" aria-label="Notifications">
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">notifications</span>
        @if($unread > 0)
            <span class="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">{{ $unread }}</span>
        @endif
    </button>

    <div x-cloak x-show="open" x-transition
         class="absolute right-0 mt-2 w-[340px] overflow-hidden rounded-xl border border-line bg-elevated shadow-raised">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-ink">Alerts</p>
                <p class="text-xs text-muted">{{ $unread }} unread</p>
            </div>
            @if($unread > 0)
                <button wire:click="markAllRead" class="text-xs font-medium text-accent hover:underline">Mark all read</button>
            @endif
        </div>

        <div class="max-h-[360px] overflow-y-auto">
            @forelse($alerts as $alert)
                <div class="border-b border-line px-4 py-3 last:border-0 {{ $alert->is_read ? 'opacity-60' : '' }}">
                    <div class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-line bg-surface">
                            <span class="material-symbols-outlined text-[13px] {{ $alert->severity === 'critical' || $alert->severity === 'high' ? 'text-danger' : ($alert->severity === 'moderate' ? 'text-warning' : 'text-accent') }}" aria-hidden="true">
                                {{ $alert->type === 'credential_leak' ? 'key' : ($alert->type === 'reappeared' ? 'restart_alt' : 'search') }}
                            </span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium leading-snug text-ink">{{ $alert->title }}</p>
                            <p class="mt-0.5 line-clamp-2 text-[11px] leading-relaxed text-muted">{{ $alert->message }}</p>
                            <div class="mt-1.5 flex items-center gap-3">
                                @if($alert->action_url)
                                    <a href="{{ $alert->action_url }}" wire:click="markRead({{ $alert->id }})" class="text-[11px] font-medium text-accent hover:underline">Open</a>
                                @endif
                                @unless($alert->is_read)
                                    <button wire:click="markRead({{ $alert->id }})" class="text-[11px] text-muted hover:text-ink">Dismiss</button>
                                @endunless
                                <span class="ml-auto text-[10px] text-faint">{{ $alert->created_at->diffForHumans(short: true) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <span class="material-symbols-outlined text-2xl text-muted" aria-hidden="true">notifications_off</span>
                    <p class="mt-2 text-xs text-muted">No alerts yet. Monitoring will surface changes here.</p>
                </div>
            @endforelse
        </div>

        <a href="{{ route('alerts') }}" class="block border-t border-line px-4 py-2.5 text-center text-xs font-medium text-accent hover:bg-surface">View monitoring and alerts</a>
    </div>
</div>
