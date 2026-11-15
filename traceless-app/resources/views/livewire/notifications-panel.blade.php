<div class="relative" x-data="{ open: false }" @click.outside="open = false" wire:poll.20s>
    <button @click="open = !open" class="relative flex h-9 w-9 items-center justify-center rounded-full border border-hairline bg-elevated text-charcoal transition hover:shadow-glass-sm" :aria-expanded="open" aria-label="Notifications">
        <span class="material-symbols-outlined text-[19px]">notifications</span>
        @if($unread > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-coral px-1 font-mono text-[9px] font-semibold text-ink">{{ $unread }}</span>
        @endif
    </button>

    <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-[340px] overflow-hidden rounded-2xl border border-hairline bg-elevated shadow-glass backdrop-blur-xl">
        <div class="flex items-center justify-between border-b border-hairline px-4 py-3">
            <div>
                <p class="font-body text-sm font-semibold">Alerts</p>
                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-muted">{{ $unread }} unread</p>
            </div>
            @if($unread > 0)
                <button wire:click="markAllRead" class="font-mono text-[10px] text-electric-blue hover:underline">Mark all read</button>
            @endif
        </div>
        <div class="max-h-[360px] overflow-y-auto scrollbar-thin">
            @forelse($alerts as $alert)
                <div class="border-b border-hairline px-4 py-3 last:border-0 {{ $alert->is_read ? 'opacity-60' : '' }}">
                    <div class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-hairline bg-elevated">
                            <span class="material-symbols-outlined text-[13px] {{ $alert->severity === 'critical' || $alert->severity === 'high' ? 'text-coral' : ($alert->severity === 'moderate' ? 'text-amber' : 'text-electric-blue') }}">
                                {{ $alert->type === 'credential_leak' ? 'key' : ($alert->type === 'reappeared' ? 'restart_alt' : 'radar') }}
                            </span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-body text-xs font-medium leading-snug text-ink">{{ $alert->title }}</p>
                            <p class="mt-0.5 line-clamp-2 font-body text-[11px] leading-relaxed text-muted">{{ $alert->message }}</p>
                            <div class="mt-1.5 flex items-center gap-3">
                                @if($alert->action_url)
                                    <a href="{{ $alert->action_url }}" wire:click="markRead({{ $alert->id }})" class="font-mono text-[9px] uppercase tracking-wide text-electric-blue hover:underline">Open</a>
                                @endif
                                @unless($alert->is_read)
                                    <button wire:click="markRead({{ $alert->id }})" class="font-mono text-[9px] uppercase tracking-wide text-muted hover:text-ink">Dismiss</button>
                                @endunless
                                <span class="ml-auto font-mono text-[9px] text-muted">{{ $alert->created_at->diffForHumans(short: true) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center">
                    <span class="material-symbols-outlined text-2xl text-muted">notifications_off</span>
                    <p class="mt-2 font-mono text-[10px] text-muted">No alerts yet. Monitoring will surface changes here.</p>
                </div>
            @endforelse
        </div>
        <a href="{{ route('alerts') }}" class="block border-t border-hairline px-4 py-2.5 text-center font-mono text-[10px] text-electric-blue hover:bg-elevated">View monitoring &amp; alerts</a>
    </div>
</div>
