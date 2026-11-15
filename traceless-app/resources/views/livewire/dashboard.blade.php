<div class="space-y-8">
    @if(!$profile)
        <!-- Identity Initialization Banner -->
        <div class="ops-card p-10 text-center max-w-xl mx-auto space-y-4">
            <span class="material-symbols-outlined text-4xl text-[#55D6E8]">fingerprint</span>
            <h2 class="font-display text-2xl font-semibold tracking-[-0.02em] text-white">Establish your monitored identity</h2>
            <p class="text-sm text-[#A6B0BC] leading-relaxed">
                Before deploying automated OSINT discovery, link your target identifiers under explicit legal authorization.
            </p>
            <div class="pt-2">
                <a href="{{ route('identity.create') }}" class="btn-ops-primary text-xs py-3 px-6">
                    <span>Initialize Profile</span>
                    <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </a>
            </div>
        </div>
    @else
        <!-- Identity Header / Operational Status Bar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-white/[0.08]">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="ops-badge ops-badge-cyan">PROFILE ACTIVE</span>
                    <span class="font-mono text-[10px] text-[#5D6B7A]">ID: TR-{{ str_pad($profile->id, 5, '0', STR_PAD_LEFT) }}</span>
                </div>
                <h1 class="font-display text-2xl font-semibold tracking-[-0.03em] text-white sm:text-3xl">{{ $profile->full_name }}</h1>
                <div class="font-mono text-xs text-[#A6B0BC] flex items-center gap-4">
                    <span>Target: {{ $profile->primary_email ?? 'No Primary Email' }}</span>
                    <span>Region: {{ $profile->region ?? 'Global' }}</span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button wire:click="triggerQuickScan" class="btn-ops-primary text-xs py-2.5 px-4">
                    <span class="material-symbols-outlined text-[16px]">radar</span>
                    <span>Execute Recon Scan</span>
                </button>
                <a href="{{ route('reports') }}" class="btn-ops-secondary text-xs py-2.5 px-3">
                    <span class="material-symbols-outlined text-[16px]">article</span>
                    <span>Dossier</span>
                </a>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Privacy Score Dial -->
            <div class="ops-card p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-mono text-[#A6B0BC]">
                    <span>POSTURE INDEX</span>
                    <span class="material-symbols-outlined text-[16px] text-[#55D6E8]">speed</span>
                </div>
                <div class="my-4 flex items-baseline gap-2">
                    <span class="font-mono text-4xl font-bold text-white">{{ $scoreData['privacy_score'] ?? $profile->privacy_score }}</span>
                    <span class="font-mono text-xs text-[#5D6B7A]">/ 100</span>
                </div>
                <div class="w-full h-1.5 bg-[#080A0D] rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#55D6E8] to-[#4ADE9B]" style="width: {{ $scoreData['privacy_score'] ?? $profile->privacy_score }}%"></div>
                </div>
                <div class="mt-3 font-mono text-[9px] text-[#5D6B7A] leading-tight">
                    {{ $scoreData['disclaimer'] ?? 'Score is an estimate for prioritizing action.' }}
                </div>
            </div>

            <!-- Total Findings -->
            <div class="ops-card p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-mono text-[#A6B0BC]">
                    <span>INDEXED NODES</span>
                    <span class="material-symbols-outlined text-[16px] text-[#A6B0BC]">hub</span>
                </div>
                <div class="my-4 font-mono text-4xl font-bold text-white">
                    {{ $scoreData['total_findings'] ?? 0 }}
                </div>
                <div class="font-mono text-[10px] text-[#A6B0BC] flex items-center justify-between">
                    <span>Public Accounts & Traces</span>
                    <a href="{{ route('findings') }}" class="text-[#55D6E8] hover:underline">View →</a>
                </div>
            </div>

            <!-- Elevated Risk Alerts -->
            <div class="ops-card p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-mono text-[#A6B0BC]">
                    <span>ELEVATED THREATS</span>
                    <span class="material-symbols-outlined text-[16px] text-[#EF6262]">warning</span>
                </div>
                <div class="my-4 font-mono text-4xl font-bold text-[#EF6262]">
                    {{ ($scoreData['critical_count'] ?? 0) + ($scoreData['high_count'] ?? 0) }}
                </div>
                <div class="font-mono text-[10px] text-[#A6B0BC] flex items-center justify-between">
                    <span>High-risk records</span>
                    <span class="ops-badge ops-badge-coral text-[8px]">ACTION REQ</span>
                </div>
            </div>

            <!-- Removals In-Flight -->
            <div class="ops-card p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-mono text-[#A6B0BC]">
                    <span>OPT-OUT QUEUE</span>
                    <span class="material-symbols-outlined text-[16px] text-[#4ADE9B]">task_alt</span>
                </div>
                <div class="my-4 font-mono text-4xl font-bold text-[#4ADE9B]">
                    {{ $activeTasks->where('status', 'removed')->count() }}<span class="text-sm font-normal text-[#5D6B7A]">/{{ $activeTasks->count() }}</span>
                </div>
                <div class="font-mono text-[10px] text-[#A6B0BC] flex items-center justify-between">
                    <span>Remediated Listings</span>
                    <a href="{{ route('removals') }}" class="text-[#55D6E8] hover:underline">Removals →</a>
                </div>
            </div>
        </div>

        <!-- Recent Reconnaissance Feed & Credential Alerts -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Findings Feed -->
            <div class="lg:col-span-8 ops-card p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-white/[0.08] pb-3">
                    <div class="flex items-center gap-2 font-mono text-xs font-semibold text-white">
                        <span class="material-symbols-outlined text-[#55D6E8] text-[16px]">radar</span>
                        <span>RECENT DISCOVERED NODES</span>
                    </div>
                    <a href="{{ route('findings') }}" class="font-mono text-[11px] text-[#55D6E8] hover:underline">Full Report →</a>
                </div>

                @if($recentFindings->count())
                    <div class="divide-y divide-white/[0.06]">
                        @foreach($recentFindings as $f)
                            <div class="py-3 flex items-center justify-between gap-4 font-mono text-xs">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-[18px] 
                                        @if($f->risk === 'critical') text-[#EF6262]
                                        @elseif($f->risk === 'high') text-[#E9B45D]
                                        @else text-[#55D6E8] @endif">
                                        @if($f->category === 'data_broker') corporate_fare
                                        @elseif($f->category === 'public_paste') article
                                        @else account_box @endif
                                    </span>
                                    <div>
                                        <div class="text-white font-medium">{{ $f->source }}</div>
                                        <div class="text-[10px] text-[#5D6B7A]">{{ $f->identifier_value }} // {{ str_replace('_', ' ', strtoupper($f->category)) }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="ops-badge 
                                        @if($f->risk === 'critical') ops-badge-coral
                                        @elseif($f->risk === 'high') ops-badge-amber
                                        @else ops-badge-cyan @endif">
                                        {{ strtoupper($f->risk) }}
                                    </span>
                                    @if($f->url)
                                        <a href="{{ $f->url }}" target="_blank" rel="noopener" class="text-[#A6B0BC] hover:text-white" title="Open source">
                                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center font-mono text-xs text-[#5D6B7A]">
                        No intelligence nodes detected. Click "Execute Recon Scan" above.
                    </div>
                @endif
            </div>

            <!-- Credential Exposures Sidepanel -->
            <div class="lg:col-span-4 ops-card p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-white/[0.08] pb-3">
                    <div class="flex items-center gap-2 font-mono text-xs font-semibold text-white">
                        <span class="material-symbols-outlined text-[#E9B45D] text-[16px]">vpn_key</span>
                        <span>BREACH METADATA</span>
                    </div>
                    <span class="ops-badge ops-badge-faint text-[9px]">ZERO PASSWORDS</span>
                </div>

                @if($credentialExposures->count())
                    <div class="space-y-3 font-mono text-xs">
                        @foreach($credentialExposures as $c)
                            <div class="p-3 rounded bg-[#080A0D] border border-white/5 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-white font-semibold">{{ $c->service_name }}</span>
                                    @if($c->is_resolved)
                                        <span class="text-[#4ADE9B] text-[10px]">RESOLVED</span>
                                    @else
                                        <button wire:click="markResolved({{ $c->id }})" class="ops-badge ops-badge-amber text-[9px] hover:bg-[#E9B45D]/20">
                                            Fix & Rotate
                                        </button>
                                    @endif
                                </div>
                                <div class="text-[10px] text-[#A6B0BC]">Exposed: {{ $c->email }}</div>
                                <div class="text-[9px] text-[#5D6B7A]">Date: {{ $c->breach_date ?? 'Archived' }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    @if(config('traceless.breach.provider'))
                        <div class="py-12 text-center font-mono text-xs text-[#4ADE9B]">
                            ✓ No active credential exposures found across public disclosure lists.
                        </div>
                    @else
                        <div class="py-12 text-center font-mono text-xs leading-relaxed text-[#5D6B7A]">
                            <span class="mb-2 block text-[#E9B45D]">PROVIDER NOT CONFIGURED</span>
                            Breach metadata is not enabled in this environment. TraceLess shows nothing here rather than infer or invent exposures.
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
