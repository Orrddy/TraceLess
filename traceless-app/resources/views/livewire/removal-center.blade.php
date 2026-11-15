<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-white/[0.08]">
        <div>
            <div class="ops-badge ops-badge-amber mb-2">Remediation Ops</div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Removal Action Center</h1>
            <p class="font-mono text-xs text-[#A6B0BC] mt-1">Track opt-out requests, automated broker delistings, and privacy playbooks.</p>
        </div>
    </div>

    <!-- Status Filters -->
    @if($tasks->count())
        <div class="flex flex-wrap gap-2 font-mono text-[10px]">
            @php $grouped = $tasks->groupBy('status'); @endphp
            @foreach(['ready_to_remove' => 'Ready to Remove', 'in_progress' => 'In Progress', 'waiting_for_source' => 'Waiting for Source', 'removed' => 'Verified Removed'] as $key => $lbl)
                <span class="px-3 py-1.5 rounded-lg border 
                    @if($key === 'removed') border-[#4ADE9B]/30 bg-[#4ADE9B]/10 text-[#4ADE9B]
                    @elseif($key === 'in_progress') border-[#55D6E8]/30 bg-[#55D6E8]/10 text-[#55D6E8]
                    @else border-white/10 bg-white/5 text-[#A6B0BC] @endif">
                    {{ $lbl }}: <strong class="text-white">{{ $grouped->get($key)?->count() ?? 0 }}</strong>
                </span>
            @endforeach
        </div>
    @endif

    <!-- Task List -->
    <div class="space-y-3">
        @forelse($tasks as $t)
            <div class="ops-card p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-white text-sm font-sans">{{ $t->source_name }}</span>
                        <span class="ops-badge ops-badge-faint text-[9px]">{{ strtoupper($t->workflow_type) }} WORKFLOW</span>
                    </div>
                    <div class="font-mono text-xs text-[#A6B0BC]">
                        Turnaround: {{ $t->estimated_turnaround }} // Target: {{ $t->finding?->identifier_value }}
                    </div>
                    @if($t->opt_out_url)
                        <div class="pt-1">
                            <a href="{{ $t->opt_out_url }}" target="_blank" rel="noopener" class="font-mono text-[10px] text-[#55D6E8] hover:underline flex items-center gap-1">
                                <span>Direct Opt-Out Procedure Link</span>
                                <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                            </a>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    @if($t->status !== 'removed')
                        <select wire:change="updateTaskStatus({{ $t->id }}, $event.target.value)" class="ops-input py-1.5 px-3 text-xs">
                            @foreach(['ready_to_remove', 'in_progress', 'waiting_for_source', 'user_action_required', 'removed', 'could_not_verify'] as $s)
                                <option value="{{ $s }}" @if($t->status === $s) selected @endif>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                    @else
                        <span class="ops-badge ops-badge-emerald text-xs">
                            <span class="material-symbols-outlined text-[14px]">check</span>
                            <span>REMOVED</span>
                        </span>
                    @endif
                </div>
            </div>
        @empty
            <div class="ops-card p-12 text-center font-mono text-xs text-[#5D6B7A]">
                No active removal workflows. High-risk finding detections will populate this queue automatically.
            </div>
        @endforelse
    </div>
</div>
