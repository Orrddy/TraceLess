<div class="relative min-h-[75vh] flex items-center justify-center overflow-hidden rounded-2xl border border-line" wire:poll.1500ms>
    <!-- Background Looping Scanner Video (bg neon.mp4) -->
    <video width="1920" height="1080" autoplay loop muted playsinline class="absolute inset-0 h-full w-full object-cover object-center opacity-30 z-0 pointer-events-none">
        <source src="{{ asset('assets/videos/scanner-loop.mp4') }}" type="video/mp4">
    </video>

    <!-- Scrim keeps the progress copy readable over the loop -->
    <div class="absolute inset-0 z-0 bg-canvas/85"></div>

    <!-- Central Operational Console -->
    <div class="relative z-10 max-w-xl w-full p-8 text-center space-y-6">
        <!-- Classification -->
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded bg-surface/90 border border-[#55D6E8]/30 font-mono text-[10px] uppercase tracking-widest text-accent">
            <span class="h-1.5 w-1.5 rounded-full bg-accent animate-ping"></span>
            <span>SCAN PROTOCOL // #{{ $scan->id }}</span>
        </div>

        <!-- Progress Ring / Percentage -->
        <div class="relative w-40 h-40 mx-auto flex items-center justify-center">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(255, 255, 255, 0.08)" stroke-width="6"/>
                @php
                    $pct = $scan->progress_percentage;
                    $circ = 2 * 3.14159 * 50;
                    $off = $circ - ($circ * $pct / 100);
                    $color = $scan->status === 'completed' ? '#4ADE9B' : '#55D6E8';
                @endphp
                <circle cx="60" cy="60" r="50" fill="none" stroke="{{ $color }}" stroke-width="6"
                        stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $off }}"
                        stroke-linecap="round" class="transition-all duration-500 ease-out"/>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="font-mono text-3xl font-bold text-ink tracking-tight">{{ $pct }}%</span>
                <span class="font-mono text-[9px] uppercase tracking-wider text-body">TELEMETRY</span>
            </div>
        </div>

        <!-- Stage Copy from Backend -->
        <div class="space-y-2">
            <h2 class="text-xl font-bold text-ink tracking-tight font-mono uppercase">
                {{ $scan->current_stage }}
            </h2>
            <div class="font-mono text-xs text-body space-x-4">
                <span>STATUS: <strong class="text-ink">{{ strtoupper($scan->status) }}</strong></span>
                <span>SOURCES PROBED: <strong class="text-accent">{{ $scan->sources_checked }}</strong></span>
                <span>NODES FOUND: <strong class="text-warning">{{ $scan->findings_count }}</strong></span>
            </div>
        </div>

        <!-- Dynamic Action upon Completion -->
        @if($scan->status === 'completed')
            <div class="pt-4 animate-fade-in">
                <a href="{{ route('dashboard') }}" class="btn-ops-primary text-xs py-3 px-8 inline-flex items-center gap-2">
                    <span>Inspect Results in Command Center</span>
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
            </div>
        @else
            <div class="font-mono text-[10px] text-faint">
                Checking public sources. No passwords are queried.
            </div>
        @endif
    </div>
</div>
