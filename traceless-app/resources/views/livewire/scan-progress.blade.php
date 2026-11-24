{{-- Scan console: 3D Earth, server-timed countdown, live stage telemetry. --}}
@php
    $eta = $scan->estimatedCompletionAt();
    $active = $scan->isActive();
    $hours = max(1, (int) ceil($scan->estimatedDurationSeconds() / 3600));
    $remaining = $eta ? max(0, $eta->getTimestamp() - now()->getTimestamp()) : null;
    $timerText = $remaining !== null ? sprintf('%02d:%02d:%02d', intdiv($remaining, 3600), intdiv($remaining % 3600, 60), $remaining % 60) : '—';
@endphp

<div class="scan-page"
     data-scan-fx
     data-status="{{ $scan->status }}"
     data-active="{{ $active ? '1' : '0' }}"
     data-type="{{ $scan->type }}"
     data-eta-seconds="{{ $scan->estimatedDurationSeconds() }}"
     data-progress="{{ $scan->progress_percentage }}"
     data-stage="{{ $scan->current_stage }}"
     data-server-now-ms="{{ now()->getTimestampMs() }}"
     data-deadline-ms="{{ $eta?->getTimestampMs() }}"
     data-status-url="{{ route('scans.status', $scan) }}"
     data-texture="{{ asset('assets/earth/earth-map.jpg') }}">

    <div class="scan-grid" aria-hidden="true"></div>
    <div class="scan-earth" data-earth aria-hidden="true"></div>
    <div class="scan-vignette" aria-hidden="true"></div>

    <div class="scan-hud">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="scan-pill"><span class="scan-live-dot"></span>SCAN PROTOCOL // #{{ $scan->id }}</span>
                <span class="scan-pill scan-pill-muted" data-status-pill>{{ strtoupper($scan->status) }}</span>
                <span class="scan-pill scan-pill-muted uppercase">{{ $scan->type === 'deep_scan' ? 'Deep crawl' : 'Snapshot' }}</span>
            </div>
            <div class="flex items-center gap-2">
                @if($active)
                    <button type="button"
                            wire:click="cancel"
                            wire:confirm="Cancel this scan? Nothing further will be checked."
                            class="scan-button">
                        Stop scan
                    </button>
                @endif
                <a href="{{ route('dashboard') }}" class="scan-button scan-button-ghost">Command center</a>
            </div>
        </div>

        <div class="max-w-xl">
            <p class="scan-kicker">PUBLIC-SURFACE CRAWL</p>
            <h1 class="scan-title">{{ $active ? 'Sweeping the public web' : ($scan->status === 'completed' ? 'Sweep complete' : 'Scan stopped') }}</h1>
            <p class="scan-sub" data-stage-label>{{ $scan->current_stage ?: 'Queued on the scan mesh' }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
            <div class="space-y-4">
                <div>
                    <div class="scan-kicker">{{ $active ? 'EST. TIME REMAINING' : 'FINAL DURATION' }}</div>
                    <div class="scan-timer" data-timer>{{ $active ? $timerText : $scan->durationForHumans() }}</div>
                    @if($active)
                        <p class="scan-hint">This sweep can take up to {{ $hours }} hours. It runs on the server — close the tab, sign out, or open another device and the same countdown continues.</p>
                    @endif
                </div>

                <div>
                    <div class="scan-bar"><span data-progress-fill style="width: {{ $scan->progress_percentage }}%"></span></div>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2 font-mono text-[10px] uppercase tracking-widest text-[#8ab8cc]">
                        <span data-stage-ticker>{{ $scan->current_stage ?: 'Waiting for a worker' }}</span>
                        <span><span data-progress-value>{{ $scan->progress_percentage }}</span>%</span>
                    </div>
                </div>

                <dl class="grid grid-cols-3 gap-3 font-mono text-[10px] uppercase tracking-widest text-[#8ab8cc]">
                    <div class="scan-stat"><dt>Sources probed</dt><dd data-stat-sources>{{ $scan->sources_checked }}/{{ $scan->sources_total ?: '—' }}</dd></div>
                    <div class="scan-stat"><dt>Records found</dt><dd data-stat-findings>{{ $scan->findings_count }}</dd></div>
                    <div class="scan-stat"><dt>Confidence</dt><dd>{{ $scan->status === 'completed' ? 'READY' : 'LIVE' }}</dd></div>
                </dl>
            </div>

            <ol class="scan-stages">
                @foreach([
                    'Booting the crawl mesh',
                    'Scanning the public web layer',
                    'Scraping indexed source mirrors',
                    'Pinging broker endpoints',
                    'Matching identity records',
                    'Scoring the evidence graph',
                ] as $stage)
                    <li data-stage-item class="scan-stage-item {{ $loop->first ? 'is-active' : '' }}">
                        <span class="scan-stage-dot"></span>
                        <span>{{ $stage }}</span>
                    </li>
                @endforeach
            </ol>
        </div>

        @if($scan->status === 'completed')
            <div class="scan-result">
                <p class="scan-kicker">NO FURTHER ACTION REQUIRED</p>
                <h2 class="scan-result-title">{{ $scan->findings_count }} public record{{ $scan->findings_count === 1 ? '' : 's' }} found</h2>
                <p class="scan-result-copy">Every match carries the source, evidence and a removal path. Review them in the command center.</p>
                <a href="{{ route('dashboard') }}" class="scan-button scan-button-primary">Open results</a>
            </div>
        @elseif(in_array($scan->status, ['failed', 'cancelled'], true))
            <div class="scan-result">
                <p class="scan-kicker">SCAN STOPPED</p>
                <h2 class="scan-result-title">{{ $scan->status === 'cancelled' ? 'Cancelled' : 'The scan could not finish' }}</h2>
                <p class="scan-result-copy">{{ $scan->error_message ?: 'No partial results were published. You can start a fresh scan from the command center.' }}</p>
                <a href="{{ route('dashboard') }}" class="scan-button scan-button-primary">Back to command center</a>
            </div>
        @endif
    </div>
</div>
