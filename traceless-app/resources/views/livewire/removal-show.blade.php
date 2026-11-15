<div class="space-y-6">
    <div class="flex flex-wrap items-center gap-2 font-mono text-[10px] text-muted">
        <a href="{{ route('removals') }}" class="inline-flex items-center gap-1 hover:text-electric-blue">
            <span class="material-symbols-outlined text-[14px]">arrow_back</span> Removal actions
        </a>
        <span class="text-muted/40">/</span>
        <span>Task #{{ $task->id }}</span>
    </div>

    <x-page-header eyebrow="Removal dossier" :title="$task->source_name" description="A tracked opt-out request with the exact manual steps, evidence trail and current source status.">
        <x-status-badge :status="$task->status" />
        @if($task->finding)
            <x-risk-badge :risk="$task->finding->risk" />
        @endif
    </x-page-header>

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Workflow timeline --}}
        <div class="glass-card p-5 lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <p class="mono-label">Workflow trajectory</p>
                <span class="font-mono text-[10px] text-muted">{{ $task->estimated_turnaround ?? 'Varies by source' }}</span>
            </div>

            <ol class="mt-5 space-y-0">
                @foreach([
                    ['ready_to_remove', 'Queued', 'Task created from a high-risk finding.'],
                    ['in_progress', 'Request submitted', 'Opt-out sent or manual request logged.'],
                    ['waiting_for_source', 'Awaiting source', 'The source is processing the request.'],
                    ['removed', 'Verified removed', 'The public record no longer resolves.'],
                ] as $i => [$key, $label, $hint])
                    @php
                        $order = ['ready_to_remove' => 0, 'in_progress' => 1, 'waiting_for_source' => 2, 'removed' => 3];
                        $current = $order[$task->status] ?? 0;
                        $done = $current > $i;
                        $active = $current === $i;
                    @endphp
                    <li class="relative flex gap-4 pb-6 last:pb-0">
                        @if(!$loop->last)
                            <span class="absolute left-[13px] top-7 h-[calc(100%-1.75rem)] w-px {{ $done ? 'bg-emerald/40' : 'bg-hairline' }}"></span>
                        @endif
                        <span class="relative z-10 mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border
                            {{ $done ? 'border-emerald/30 bg-emerald/10 text-emerald' : ($active ? 'border-electric-blue/40 bg-electric-blue/10 text-electric-blue' : 'border-hairline bg-elevated text-muted') }}">
                            <span class="material-symbols-outlined text-[14px]">{{ $done ? 'check' : ($active ? 'radio_button_checked' : 'circle') }}</span>
                        </span>
                        <div>
                            <p class="font-body text-sm font-medium {{ $done || $active ? 'text-ink' : 'text-muted' }}">{{ $label }}</p>
                            <p class="mt-0.5 font-body text-xs leading-relaxed text-muted">{{ $hint }}</p>
                            @if($key === 'removed' && $task->completed_at)
                                <p class="mt-1 font-mono text-[10px] text-emerald">{{ $task->completed_at->format('d M Y · H:i') }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>

            @if(in_array($task->status, ['user_action_required', 'could_not_verify', 'reappeared'], true))
                <div class="mt-2 rounded-xl border border-amber/25 bg-amber/10 px-4 py-3">
                    <p class="font-mono text-[10px] font-semibold text-warning">This task needs your intervention</p>
                    <p class="mt-1 font-body text-xs leading-relaxed text-warning/85">
                        @if($task->status === 'reappeared')
                            The source republished this record. Re-submit the opt-out and document the reappearance.
                        @elseif($task->status === 'could_not_verify')
                            The source could not confirm removal. Keep the evidence and retry after the stated turnaround.
                        @else
                            Complete the source's verification step (email link, CAPTCHA or account confirmation) to continue.
                        @endif
                    </p>
                </div>
            @endif

            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-hairline pt-4">
                <label class="mono-label" for="removalStatus">Move status</label>
                <select id="removalStatus" wire:model="statusDraft" class="select-base ml-auto">
                    @foreach(\App\Enums\RemovalStatus::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
                <button wire:click="applyStatus" class="btn-secondary !py-2.5">
                    <span class="material-symbols-outlined text-[15px]">published_with_changes</span> Apply
                </button>
            </div>
            @error('statusDraft')<p class="mt-2 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
        </div>

        {{-- Source facts --}}
        <div class="space-y-5">
            <div class="glass-card p-5">
                <p class="mono-label">Source record</p>
                <dl class="mt-4 space-y-3 font-body text-xs">
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Workflow</dt>
                        <dd class="text-right font-medium text-ink">{{ ucfirst(str_replace('_', ' ', $task->workflow_type)) }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Turnaround</dt>
                        <dd class="text-right font-medium text-ink">{{ $task->estimated_turnaround ?? 'Unknown' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Requested</dt>
                        <dd class="text-right font-mono text-[10px] text-ink">{{ $task->requested_at?->format('d M Y') ?? 'Not yet' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-muted">Finding</dt>
                        <dd class="text-right">
                            @if($task->finding)
                                <a href="{{ route('findings.show', $task->finding) }}" class="font-mono text-[10px] text-electric-blue hover:underline">{{ Str::limit($task->finding->identifier_value, 26) }}</a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if($task->opt_out_url)
                    <a href="{{ $task->opt_out_url }}" target="_blank" rel="noopener noreferrer" class="btn-primary mt-4 w-full">
                        <span class="material-symbols-outlined text-[16px]">open_in_new</span> Open opt-out page
                    </a>
                @endif
            </div>

            <div class="glass-card p-5">
                <p class="mono-label">Notes &amp; evidence</p>
                <textarea wire:model="notes" rows="4" class="input-base mt-3 resize-none" placeholder="Reference numbers, verification emails, dates…"></textarea>
                @error('notes')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
                <button wire:click="saveNotes" class="btn-secondary mt-3 w-full">Save notes</button>
            </div>
        </div>
    </div>

    {{-- Manual instructions --}}
    <div class="glass-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="mono-label">Manual playbook</p>
                <h2 class="mt-1 font-editorial text-2xl text-ink">{{ $instruction->title ?? 'Source-specific removal steps' }}</h2>
            </div>
            <span class="badge badge-neutral">
                <span class="material-symbols-outlined text-[12px]">menu_book</span>
                {{ $instruction->typical_turnaround ?? 'Varies' }}
            </span>
        </div>

        @if($instruction)
            <ol class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach([$instruction->step_1, $instruction->step_2, $instruction->step_3, $instruction->step_4] as $step)
                    @continue(!$step)
                    <li class="glass-inset flex gap-3 p-4">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-ink font-mono text-[10px] font-semibold text-ink">{{ $loop->iteration }}</span>
                        <p class="font-body text-xs leading-relaxed text-charcoal">{{ $step }}</p>
                    </li>
                @endforeach
            </ol>
            @if($instruction->direct_opt_out_link)
                <a href="{{ $instruction->direct_opt_out_link }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-1.5 font-mono text-[11px] text-electric-blue hover:underline">
                    Direct opt-out link <span class="material-symbols-outlined text-[14px]">north_east</span>
                </a>
            @endif
        @else
            <p class="mt-4 rounded-xl border border-hairline bg-elevated p-4 font-body text-xs leading-relaxed text-muted">
                No curated playbook exists for this source yet. Use the source's privacy policy or contact page, reference the specific record URL, and keep a copy of your request. Removal is not guaranteed.
            </p>
        @endif

        <div class="mt-5 flex items-start gap-2 rounded-xl border border-hairline bg-canvas-alt px-4 py-3">
            <span class="material-symbols-outlined text-[16px] text-muted">info</span>
            <p class="font-body text-[11px] leading-relaxed text-muted">TraceLess tracks requests but cannot compel a third party to remove data. Never send passwords, government IDs or payment details to a data broker.</p>
        </div>
    </div>
</div>
