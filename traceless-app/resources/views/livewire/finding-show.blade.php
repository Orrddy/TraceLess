<div class="mx-auto max-w-5xl space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('findings') }}" class="btn-ghost !px-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span> Findings</a>
        <div class="flex items-center gap-2">
            <x-risk-badge :risk="$finding->risk" />
            <x-status-badge :status="$finding->status" />
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-12">
        <div class="space-y-4 lg:col-span-8">
            <div class="glass-card overflow-hidden">
                <div class="border-b border-hairline bg-elevated px-6 py-6">
                    <div class="flex items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-hairline bg-elevated text-charcoal">
                            <span class="material-symbols-outlined text-[22px]">
                                {{ $finding->category === 'data_broker' ? 'corporate_fare' : ($finding->category === 'public_paste' ? 'content_paste' : ($finding->category === 'developer_profile' ? 'code' : 'person_search')) }}
                            </span>
                        </span>
                        <div class="min-w-0">
                            <p class="eyebrow">{{ str($finding->category)->replace('_', ' ') }}</p>
                            <h1 class="mt-1 font-editorial text-2xl leading-tight text-ink sm:text-3xl">{{ $finding->title }}</h1>
                            <p class="mt-1 font-mono text-[10px] text-muted">{{ $finding->source }} · {{ $finding->identifier_type }} <span class="text-charcoal">{{ $finding->identifier_value }}</span></p>
                        </div>
                    </div>

                    @if($finding->description)
                        <p class="mt-4 font-body text-sm leading-relaxed text-charcoal/85">{{ $finding->description }}</p>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @if($finding->url)
                            <a href="{{ $finding->url }}" target="_blank" rel="noopener noreferrer nofollow" class="btn-secondary !px-3 !py-2 !text-[10px]">
                                <span class="material-symbols-outlined text-[14px]">open_in_new</span> View public source
                            </a>
                        @endif
                        <span class="badge {{ $finding->removal_method === 'automated_request' ? 'badge-low' : 'badge-neutral' }}">
                            {{ $finding->removal_method === 'automated_request' ? 'Automated request supported' : 'Manual instructions' }}
                        </span>
                        <span class="badge badge-neutral">Confidence {{ number_format($finding->confidence * 100) }}%</span>
                        @if($finding->verification_status === 'verified')
                            <span class="badge badge-low">Verified · HTTP {{ $finding->http_status ?? 200 }}</span>
                        @elseif($finding->verification_status === 'demo')
                            <span class="badge badge-neutral">Demo data</span>
                        @else
                            <span class="badge badge-neutral">Unverified</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-px bg-hairline sm:grid-cols-4">
                    @foreach([
                        ['Discovered', $finding->discovered_at?->format('M d, Y') ?? 'n/a'],
                        ['Last checked', $finding->last_checked_at?->format('M d, Y') ?? 'n/a'],
                        ['Removal method', str($finding->removal_method)->replace('_', ' ')->title()],
                        ['Scan', $finding->scan_id ? '#'.str_pad((string) $finding->scan_id, 4, '0', STR_PAD_LEFT) : 'Manual'],
                    ] as [$label, $value])
                        <div class="bg-elevated px-4 py-3">
                            <p class="mono-label">{{ $label }}</p>
                            <p class="mt-1 font-mono text-[11px] text-charcoal">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            @unless($finding->verification_status === 'demo')
                <div class="glass-card p-6">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-electric-blue">fact_check</span>
                        <h2 class="font-body text-sm font-semibold text-ink">How this was checked</h2>
                    </div>

                    @if($finding->verification_status === 'verified' && $finding->evidence)
                        <p class="mt-2 font-body text-sm leading-relaxed text-charcoal/85">
                            TraceLess requested the public page and matched the source's own existence rule.
                            This is the evidence kept for this record.
                        </p>
                        <dl class="mt-3 grid gap-px overflow-hidden rounded-xl border border-hairline bg-hairline sm:grid-cols-2">
                            @foreach([
                                ['Match rule', $finding->evidence['rule'] ?? 'n/a'],
                                ['HTTP status', $finding->evidence['http_status'] ?? 'n/a'],
                                ['Checked at', $finding->evidence['checked_at'] ?? 'n/a'],
                                ['Response hash', \Illuminate\Support\Str::limit($finding->evidence['body_sha256'] ?? 'n/a', 24)],
                            ] as [$label, $value])
                                <div class="bg-elevated px-4 py-3">
                                    <p class="mono-label">{{ $label }}</p>
                                    <p class="mt-1 break-all font-mono text-[11px] text-charcoal">{{ $value }}</p>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <p class="mt-2 font-body text-sm leading-relaxed text-charcoal/85">
                            This record has not been confirmed against the live source. Treat it as a
                            lead to review, not as a verified exposure.
                        </p>
                    @endif
                </div>
            @endunless

            <div class="glass-card p-6">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-electric-blue">lightbulb</span>
                    <h2 class="font-body text-sm font-semibold text-ink">Recommended action</h2>
                </div>
                <p class="mt-2 font-body text-sm leading-relaxed text-charcoal/85">{{ $finding->action_guidance ?: 'Review the public source and use the available opt-out or privacy settings flow.' }}</p>
                <p class="mt-3 rounded-xl border border-amber/20 bg-amber/[.05] px-3 py-2 font-mono text-[10px] leading-relaxed text-warning">Removal is not guaranteed. Some sources require identity verification or manual follow-up. TraceLess will never ask for your password.</p>
            </div>

            @if($instruction)
                <div class="glass-card overflow-hidden">
                    <div class="flex items-center justify-between border-b border-hairline px-6 py-4">
                        <div>
                            <p class="eyebrow">Removal playbook</p>
                            <h2 class="mt-1 font-editorial text-xl text-ink">{{ $instruction->title }}</h2>
                        </div>
                        <span class="badge badge-neutral">~{{ $instruction->typical_turnaround }}</span>
                    </div>
                    <ol class="divide-y divide-hairline">
                        @foreach(array_filter([$instruction->step_1, $instruction->step_2, $instruction->step_3, $instruction->step_4]) as $step)
                            <li class="flex gap-3 px-6 py-3.5">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-electric-blue/25 bg-electric-blue/10 font-mono text-[10px] font-semibold text-electric-blue">{{ $loop->iteration }}</span>
                                <p class="font-body text-sm leading-relaxed text-charcoal/85">{{ $step }}</p>
                            </li>
                        @endforeach
                    </ol>
                    @if($instruction->direct_opt_out_link)
                        <div class="border-t border-hairline px-6 py-4">
                            <a href="{{ $instruction->direct_opt_out_link }}" target="_blank" rel="noopener noreferrer nofollow" class="btn-primary !py-2.5">Open opt-out page <span class="material-symbols-outlined text-[14px]">open_in_new</span></a>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="space-y-4 lg:col-span-4">
            <div class="glass-card p-6">
                <p class="eyebrow">Removal workflow</p>
                <h2 class="mt-1 font-editorial text-xl text-ink">Take action</h2>

                @if($finding->removalTask)
                    <div class="mt-3 rounded-xl border border-hairline bg-elevated p-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-[10px] text-muted">Task #{{ $finding->removalTask->id }}</span>
                            <x-status-badge :status="$finding->removalTask->status" />
                        </div>
                        <p class="mt-1.5 font-mono text-[9px] text-muted">{{ ucfirst($finding->removalTask->workflow_type) }} · {{ $finding->removalTask->estimated_turnaround }}</p>
                        <a href="{{ route('removals.show', $finding->removalTask) }}" class="mt-2 inline-block font-mono text-[10px] text-electric-blue hover:underline">Open removal task →</a>
                    </div>
                @endif

                <div class="mt-4 space-y-2">
                    @if(!$finding->removalTask || in_array($finding->removalTask->status, ['ready_to_remove', 'not_started'], true))
                        <button wire:click="startRemoval" class="btn-primary w-full">Begin removal</button>
                    @elseif($finding->removalTask->status !== 'removed')
                        <button wire:click="markRemoved" class="btn-primary w-full">Mark as removed</button>
                    @endif
                    <button wire:click="markNotMine" wire:confirm="Mark this finding as not matching your identity? This removes its score penalty." class="btn-secondary w-full">This isn't me</button>
                </div>

                <div class="mt-4">
                    <label class="mono-label mb-1.5 block">Removal notes</label>
                    <textarea wire:model="notes" rows="3" class="input-base resize-none" placeholder="Reference number, date submitted, follow-up date…"></textarea>
                    <button wire:click="saveNotes" class="btn-secondary mt-2 w-full !py-2 !text-[10px]">Save notes</button>
                </div>
            </div>

            <div class="glass-card p-6">
                <p class="mono-label">Data handling</p>
                <ul class="mt-3 space-y-2.5 font-mono text-[10px] text-muted">
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[14px] text-emerald">check</span>Public information only</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[14px] text-emerald">check</span>No passwords requested</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[14px] text-emerald">check</span>Consent recorded for this profile</li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-[14px] text-emerald">check</span>Audit history retained</li>
                </ul>
            </div>
        </div>
    </div>
</div>
