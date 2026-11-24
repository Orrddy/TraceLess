@php
    $riskDots = [
        'critical' => 'v-dot-red',
        'high' => 'v-dot-amber',
        'moderate' => 'v-dot-blue',
        'low' => 'v-dot-gray',
    ];
    $scanDots = [
        'completed' => 'v-dot-green',
        'failed' => 'v-dot-red',
        'cancelled' => 'v-dot-gray',
        'queued' => 'v-dot-gray',
    ];
    $score = (int) ($scoreData['privacy_score'] ?? $profile?->privacy_score ?? 0);
    $elevated = (int) (($scoreData['critical_count'] ?? 0) + ($scoreData['high_count'] ?? 0));
    $findingsTotal = (int) ($scoreData['total_findings'] ?? 0);
    $removed = (int) ($removalProgress['removed'] ?? 0);
    $removalTotal = (int) ($removalProgress['total'] ?? 0);
    $removalPercent = (int) ($removalProgress['percent'] ?? 0);
    $riskTotal = max(1, array_sum($riskDistribution ?? []));
@endphp

<div class="v-page">
    @if(! $profile)
        <div class="flex min-h-[70vh] items-center justify-center">
            <div class="v-card w-full max-w-md px-8 py-10 text-center">
                <p class="v-kicker">Get started</p>
                <h1 class="v-h1 mt-2">Set up your identity</h1>
                <p class="v-body mx-auto mt-3 max-w-sm">
                    TraceLess only finds records for an identity you are legally allowed to monitor.
                    Create yours once — it takes a minute.
                </p>
                <div class="mt-6 flex items-center justify-center gap-2">
                    <a href="{{ route('identity.create') }}" class="v-btn v-btn-primary">
                        Create identity
                        <span aria-hidden="true">→</span>
                    </a>
                    <a href="{{ route('privacy') }}" class="v-btn v-btn-secondary">How it works</a>
                </div>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-5">
            {{-- Unread alerts: one quiet line each, dismissible. --}}
            @if(count($alerts))
                <div class="v-card divide-y divide-black/5">
                    @foreach($alerts as $alert)
                        @php
                            $alertDot = match ($alert->severity) {
                                'critical' => 'v-dot-red',
                                'high', 'warning' => 'v-dot-amber',
                                'info' => 'v-dot-blue',
                                default => 'v-dot-gray',
                            };
                        @endphp
                        <div class="flex items-start gap-3 px-5 py-3">
                            <span class="v-dot {{ $alertDot }} mt-1.5"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-medium text-[color:var(--v-ink)]">{{ $alert->title }}</p>
                                @if($alert->message)
                                    <p class="v-small line-clamp-2 mt-0.5 md:line-clamp-1">{{ $alert->message }}</p>
                                @endif
                            </div>
                            @if($alert->action_url)
                                <a href="{{ $alert->action_url }}" class="v-link shrink-0">View</a>
                            @endif
                            <button type="button" wire:click="dismissAlert({{ $alert->id }})"
                                    class="shrink-0 rounded p-1 text-[color:var(--v-muted)] hover:bg-[#F2F2F2] hover:text-[color:var(--v-ink)]"
                                    aria-label="Dismiss alert">
                                <span class="text-sm leading-none">×</span>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Page header --}}
            <header class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                    <p class="v-kicker">Workspace / Overview</p>
                    <h1 class="v-h1 mt-1">Overview</h1>
                    <p class="v-small mt-1.5">
                        Everything TraceLess currently knows about
                        <span class="font-medium text-[color:var(--v-ink)]">{{ $profile->full_name }}</span>.
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="v-pill">
                            <span class="v-dot v-dot-green"></span>
                            Active
                        </span>
                        <span class="v-pill">v1.0 · Beta</span>
                        <span class="v-pill">v2.0 coming</span>
                        <span class="v-pill font-[family-name:'Geist_Mono',ui-monospace,monospace]">
                            TR-{{ str_pad($profile->id, 5, '0', STR_PAD_LEFT) }}
                        </span>
                        @if($profile->primary_email)
                            <span class="v-pill">{{ $profile->primary_email }}</span>
                        @endif
                        <span class="v-pill">{{ $profile->region ?? 'Global' }}</span>
                        @if($presentedScan)
                            <a href="{{ route('scans.show', $presentedScan) }}" class="v-pill live-scan-pill" aria-label="Open the live scan">
                                <span class="v-dot v-dot-green live-dot"></span>
                                <span>{{ $presentedScan->isHoldingResults() ? 'Finalizing' : 'Scan in progress' }}</span>
                                <span class="font-[family-name:'Geist_Mono',ui-monospace,monospace] tabular-nums"
                                      data-live-deadline="{{ $presentedScan->estimatedCompletionAt()?->getTimestampMs() }}"
                                      data-live-server="{{ now()->getTimestampMs() }}">--:--:--</span>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="triggerQuickScan" class="v-btn v-btn-primary">
                        Run a scan
                        <span aria-hidden="true">→</span>
                    </button>
                    <a href="{{ route('reports') }}" class="v-btn v-btn-secondary">Dossier</a>
                    <button type="button"
                            wire:click="resetForNewScan"
                            wire:confirm="Delete every scan, finding, report and removal task for this identity? Your account and consent record are kept."
                            class="v-btn v-btn-ghost">
                        Reset data
                    </button>
                </div>
            </header>

            @if($presentedScan)
                <a href="{{ route('scans.show', $presentedScan) }}"
                   class="live-scan-card v-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                   aria-label="Open the live scan">
                    <div class="flex items-center gap-3">
                        <span class="live-lights" aria-hidden="true"><i class="g"></i><i class="p"></i><i class="r"></i><i class="b"></i></span>
                        <div>
                            <p class="text-[13px] font-medium text-[color:var(--v-ink)]">
                                {{ $presentedScan->type === 'deep_scan' ? 'Deep scan' : 'Scan' }} is running
                                @if($presentedScan->isHoldingResults()) — high traffic, finalizing @endif
                            </p>
                            <p class="v-kicker mt-0.5">
                                @if($presentedScan->isHoldingResults())
                                    Results arrive when the countdown ends; we will email you.
                                @else
                                    Live server-side crawl in progress.
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 sm:shrink-0">
                        <span class="font-[family-name:'Geist_Mono',ui-monospace,monospace] text-lg tabular-nums text-[color:var(--v-ink)]"
                              data-live-deadline="{{ $presentedScan->estimatedCompletionAt()?->getTimestampMs() }}"
                              data-live-server="{{ now()->getTimestampMs() }}">--:--:--</span>
                    </div>
                </a>
            @endif

            @error('scan')
                <p class="text-[13px] text-[#E5484D]">{{ $message }}</p>
            @enderror

            {{-- Metrics --}}
            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key metrics">
                <div class="v-card v-card-pad">
                    <div class="flex items-center justify-between">
                        <p class="v-kicker">Privacy score</p>
                        <span class="v-dot {{ $score >= 70 ? 'v-dot-green' : ($score >= 40 ? 'v-dot-amber' : 'v-dot-red') }}"></span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <p class="v-metric">{{ $score }}</p>
                        <span class="v-small">/ 100</span>
                    </div>
                    <div class="v-progress mt-4" role="img" aria-label="Privacy score {{ $score }} out of 100">
                        <span style="width: {{ min(100, max(0, $score)) }}%"></span>
                    </div>
                    <p class="v-small mt-3">Higher is safer · an estimate to prioritise action</p>
                </div>

                <div class="v-card v-card-pad">
                    <div class="flex items-center justify-between">
                        <p class="v-kicker">Public findings</p>
                        <span class="v-dot v-dot-purple"></span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <p class="v-metric">{{ $findingsTotal }}</p>
                        <span class="v-small">records</span>
                    </div>
                    <p class="v-small mt-4">
                        Across {{ count($categoryStats) }} {{ Str::plural('category', count($categoryStats)) }}
                    </p>
                    <a href="{{ route('findings') }}" class="v-link mt-1 inline-block">View findings →</a>
                </div>

                <div class="v-card v-card-pad">
                    <div class="flex items-center justify-between">
                        <p class="v-kicker">Elevated risk</p>
                        <span class="v-dot v-dot-red"></span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <p class="v-metric">{{ $elevated }}</p>
                        <span class="v-small">high &amp; critical</span>
                    </div>
                    <p class="v-small mt-4">
                        {{ (int) ($riskDistribution['critical'] ?? 0) }} critical ·
                        {{ (int) ($riskDistribution['high'] ?? 0) }} high
                    </p>
                    <a href="{{ route('findings') }}" class="v-link mt-1 inline-block">Review risks →</a>
                </div>

                <div class="v-card v-card-pad">
                    <div class="flex items-center justify-between">
                        <p class="v-kicker">Removals</p>
                        <span class="v-dot {{ $removalTotal > 0 ? 'v-dot-green' : 'v-dot-gray' }}"></span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <p class="v-metric">{{ $removed }}</p>
                        <span class="v-small">of {{ $removalTotal }} requested</span>
                    </div>
                    <div class="v-progress mt-4" role="img" aria-label="Removals {{ $removalPercent }} percent complete">
                        <span style="width: {{ min(100, max(0, $removalPercent)) }}%"></span>
                    </div>
                    <a href="{{ route('removals') }}" class="v-link mt-3 inline-block">Open removal center →</a>
                </div>
            </section>

            {{-- Findings + side rail --}}
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(300px,1fr)]">
                <section class="v-card overflow-hidden" aria-label="Recent findings">
                    <div class="flex items-center justify-between px-5 py-4">
                        <div>
                            <h2 class="v-h2">Recent findings</h2>
                            <p class="v-small mt-0.5">Public records discovered by the last scans</p>
                        </div>
                        <a href="{{ route('findings') }}" class="v-link whitespace-nowrap">View all</a>
                    </div>

                    @if(count($recentFindings))
                        <div class="hidden overflow-hidden md:block">
                            <table class="w-full table-fixed text-left">
                                <thead>
                                    <tr class="border-t border-black/5">
                                        <th scope="col" class="v-table-head px-5 py-2.5 font-medium">Source</th>
                                        <th scope="col" class="v-table-head px-3 py-2.5 font-medium">Identifier</th>
                                        <th scope="col" class="v-table-head px-3 py-2.5 font-medium">Category</th>
                                        <th scope="col" class="v-table-head px-3 py-2.5 font-medium">Risk</th>
                                        <th scope="col" class="v-table-head px-5 py-2.5 text-right font-medium">Found</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-black/5">
                                    @foreach($recentFindings as $finding)
                                        <tr class="v-row">
                                            <td class="px-5 py-3 text-[13px] font-medium text-[color:var(--v-ink)]">
                                                <a href="{{ route('findings.show', $finding) }}" class="hover:text-[color:var(--v-blue)]">
                                                    <span class="block truncate">{{ $finding->source }}</span>
                                                </a>
                                            </td>
                                            <td class="px-3 py-3 text-[13px] text-[color:var(--v-body)]">
                                                <span class="block truncate">{{ $finding->identifier_value }}</span>
                                                <span class="v-kicker block truncate">{{ $finding->identifier_type }}</span>
                                            </td>
                                            <td class="px-3 py-3 text-[13px] capitalize text-[color:var(--v-body)]">
                                                <span class="block truncate">{{ str_replace('_', ' ', $finding->category) }}</span>
                                            </td>
                                            <td class="px-3 py-3">
                                                <span class="flex items-center gap-2 text-[13px] text-[color:var(--v-body)]">
                                                    <span class="v-dot {{ $riskDots[$finding->risk] ?? 'v-dot-gray' }}"></span>
                                                    <span class="truncate">{{ ucfirst($finding->risk) }}</span>
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-5 py-3 text-right text-[13px] text-[color:var(--v-muted)]">
                                                {{ $finding->discovered_at?->shortRelativeDiffForHumans() ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile rows --}}
                        <div class="divide-y divide-black/5 border-t border-black/5 md:hidden">
                            @foreach($recentFindings as $finding)
                                <a href="{{ route('findings.show', $finding) }}" class="v-row flex items-start gap-3 px-5 py-3.5">
                                    <span class="v-dot {{ $riskDots[$finding->risk] ?? 'v-dot-gray' }} mt-1.5"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-medium text-[color:var(--v-ink)]">{{ $finding->source }}</span>
                                        <span class="v-small block truncate">{{ $finding->identifier_value }}</span>
                                        <span class="v-kicker block">{{ str_replace('_', ' ', $finding->category) }} · {{ ucfirst($finding->risk) }}</span>
                                    </span>
                                    <span class="v-small shrink-0">{{ $finding->discovered_at?->shortRelativeDiffForHumans() ?? '—' }}</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="v-empty border-t border-black/5">
                            No findings yet. Run a scan to see what is public about you.
                        </div>
                    @endif
                </section>

                <div class="flex flex-col gap-4">
                    {{-- Scan activity --}}
                    <section class="v-card overflow-hidden" aria-label="Scan activity">
                        <div class="flex items-center justify-between px-5 py-4">
                            <h2 class="v-h2">Scans</h2>
                            <a href="{{ route('scans.index') }}" class="v-link whitespace-nowrap">History</a>
                        </div>

                        @if(count($recentScans))
                            <div class="divide-y divide-black/5 border-t border-black/5">
                                @foreach($recentScans as $scan)
                                    @php
                                        $scanDot = $scanDots[$scan->status] ?? 'v-dot-blue';
                                        $scanLive = $scan->isPresentedActive();
                                    @endphp
                                    <a href="{{ route('scans.show', $scan) }}" class="v-row flex items-center gap-3 px-5 py-3">
                                        <span class="v-dot {{ $scanDot }} {{ $scanLive ? 'live-dot' : '' }}"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[13px] font-medium capitalize text-[color:var(--v-ink)]">
                                                {{ str_replace('_', ' ', $scan->type) }}
                                            </span>
                                            <span class="v-kicker block">
                                                {{ $scan->isHoldingResults() ? 'Finalizing — results soon' : $scan->statusLabel() }} · {{ $scan->created_at?->diffForHumans(short: true) }}
                                            </span>
                                        </span>
                                        <span class="v-small shrink-0">{{ (int) $scan->findings_count }} found</span>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="v-empty border-t border-black/5">No scans yet.</div>
                        @endif
                    </section>

                    {{-- Credential exposures --}}
                    <section class="v-card overflow-hidden" aria-label="Credential exposures">
                        <div class="flex items-center justify-between px-5 py-4">
                            <h2 class="v-h2">Credential exposures</h2>
                            <span class="v-pill">Zero passwords</span>
                        </div>

                        @if(count($credentialExposures))
                            <div class="divide-y divide-black/5 border-t border-black/5">
                                @foreach($credentialExposures as $exposure)
                                    <div class="flex items-start gap-3 px-5 py-3">
                                        <span class="v-dot {{ $exposure->is_resolved ? 'v-dot-green' : 'v-dot-amber' }} mt-1.5"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-[13px] font-medium text-[color:var(--v-ink)]">{{ $exposure->service_name }}</p>
                                            <p class="v-kicker truncate">{{ $exposure->email }}</p>
                                            <p class="v-kicker">Breach date: {{ $exposure->breach_date ?? 'archived' }}</p>
                                        </div>
                                        @if($exposure->is_resolved)
                                            <span class="v-small shrink-0">Resolved</span>
                                        @else
                                            <button type="button" wire:click="markResolved({{ $exposure->id }})" class="v-link shrink-0">
                                                Mark resolved
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="v-empty border-t border-black/5">
                                @if(config('traceless.breach.provider'))
                                    <span class="v-dot v-dot-green mr-1.5 inline-block align-middle"></span>
                                    No active credential exposures found.
                                @else
                                    Breach metadata is not enabled, so nothing is shown here rather than guessed.
                                @endif
                            </div>
                        @endif
                    </section>
                </div>
            </div>

            {{-- Removal progress + risk distribution --}}
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="v-card v-card-pad" aria-label="Removal pipeline">
                    <div class="flex items-center justify-between">
                        <h2 class="v-h2">Removal pipeline</h2>
                        <a href="{{ route('removals') }}" class="v-link whitespace-nowrap">Manage</a>
                    </div>

                    @if(count($activeTasks))
                        <div class="mt-4 space-y-3">
                            @foreach($activeTasks as $task)
                                @php
                                    $taskDot = match ($task->status) {
                                        'removed' => 'v-dot-green',
                                        'in_progress', 'submitted' => 'v-dot-blue',
                                        'failed' => 'v-dot-red',
                                        default => 'v-dot-gray',
                                    };
                                @endphp
                                <div class="flex items-center gap-3">
                                    <span class="v-dot {{ $taskDot }}"></span>
                                    <span class="min-w-0 flex-1 truncate text-[13px] text-[color:var(--v-ink)]">
                                        {{ $task->source_name }}
                                    </span>
                                    <span class="v-kicker">{{ str_replace('_', ' ', $task->status) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="v-small mt-3">
                            No removal requests yet. Open a finding to see the opt-out path for that source.
                        </p>
                    @endif
                </section>

                <section class="v-card v-card-pad" aria-label="Risk distribution">
                    <h2 class="v-h2">Risk distribution</h2>

                    @if($findingsTotal > 0)
                        <div class="mt-4 space-y-3">
                            @foreach(['critical' => 'Critical', 'high' => 'High', 'moderate' => 'Moderate', 'low' => 'Low'] as $riskKey => $riskLabel)
                                @php
                                    $count = (int) ($riskDistribution[$riskKey] ?? 0);
                                    $width = (int) round(($count / $riskTotal) * 100);
                                @endphp
                                <div class="flex items-center gap-3">
                                    <span class="flex w-24 items-center gap-2 text-[13px] text-[color:var(--v-body)]">
                                        <span class="v-dot {{ $riskDots[$riskKey] }}"></span>
                                        {{ $riskLabel }}
                                    </span>
                                    <div class="v-progress flex-1">
                                        <span style="width: {{ $width }}%"></span>
                                    </div>
                                    <span class="w-8 text-right text-[13px] tabular-nums text-[color:var(--v-muted)]">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="v-small mt-3">Run a scan to see how the risks split across your findings.</p>
                    @endif
                </section>
            </div>
        </div>
    @endif
</div>
