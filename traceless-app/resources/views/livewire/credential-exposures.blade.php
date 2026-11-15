<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-white/[0.08]">
        <div>
            <div class="ops-badge ops-badge-coral mb-2">Breach Intelligence</div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Credential Leak Monitoring</h1>
            <p class="font-mono text-xs text-[#A6B0BC] mt-1">Cross-referencing authorized email addresses against public leak disclosure databases.</p>
        </div>
    </div>

    <!-- Security Warning Card -->
    <div class="ops-card p-5 border-l-4 border-[#55D6E8] bg-[#0A0D12] space-y-2">
        <div class="flex items-center gap-2 font-mono text-xs font-semibold text-white">
            <span class="material-symbols-outlined text-[#55D6E8] text-[18px]">verified_user</span>
            <span>ZERO-PASSWORD REPOSITORY PROTOCOL</span>
        </div>
        <p class="text-xs text-[#A6B0BC] leading-relaxed">
            TraceLess never gathers, processes, or requests actual passwords. When a service exposure is detected below, we recommend rotating that credential on the target service immediately and enabling Multi-Factor Authentication (MFA).
        </p>
    </div>

    <!-- Exposures List -->
    <div class="space-y-4">
        @forelse($exposures as $exp)
            <div class="ops-card p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <span class="text-white font-bold text-base">{{ $exp->service_name }}</span>
                        <span class="ops-badge @if($exp->is_resolved) ops-badge-emerald @else ops-badge-coral @endif">
                            {{ $exp->is_resolved ? 'REMEDIATED' : 'ACTION REQUIRED' }}
                        </span>
                    </div>

                    <div class="font-mono text-xs text-[#A6B0BC] space-x-3">
                        <span>Target: <strong class="text-white">{{ $exp->email }}</strong></span>
                        <span>Date: {{ $exp->breach_date ?? 'Archived' }}</span>
                    </div>

                    @if($exp->exposed_data_classes)
                        <div class="font-mono text-[10px] text-[#5D6B7A]">
                            Exposed Vector Types: {{ $exp->exposed_data_classes }}
                        </div>
                    @endif

                    @if(!$exp->is_resolved)
                        <div class="pt-2 font-mono text-[11px] text-[#E9B45D] flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[14px]">info</span>
                            <span>Action: Rotate password on {{ $exp->service_name }} and enable 2FA authenticator app.</span>
                        </div>
                    @endif
                </div>

                <div>
                    @if(!$exp->is_resolved)
                        <button wire:click="markResolved({{ $exp->id }})" class="btn-ops-primary text-xs py-2 px-4 whitespace-nowrap">
                            <span class="material-symbols-outlined text-[15px]">check_circle</span>
                            <span>Mark Remediated</span>
                        </button>
                    @else
                        <div class="font-mono text-[10px] text-[#4ADE9B] flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">check</span>
                            <span>Resolved {{ $exp->resolved_at?->diffForHumans() }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="ops-card p-12 text-center font-mono text-xs text-[#4ADE9B] space-y-2">
                <span class="material-symbols-outlined text-4xl">verified</span>
                <div>All monitored identifiers are clear of active public credential breach records.</div>
            </div>
        @endforelse
    </div>
</div>
