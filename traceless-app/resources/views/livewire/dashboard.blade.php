<div class="space-y-8">
    @if(!$profile)
        <!-- Identity Initialization Banner -->
        <div class="ops-card p-10 text-center max-w-xl mx-auto space-y-4">
            <span class="material-symbols-outlined text-4xl text-accent">fingerprint</span>
            <h2 class="font-display text-2xl font-semibold tracking-[-0.02em] text-ink">Establish your monitored identity</h2>
            <p class="text-sm text-body leading-relaxed">
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
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-line">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="ops-badge ops-badge-cyan">PROFILE ACTIVE</span>
                    <span class="font-mono text-[10px] text-faint">ID: TR-{{ str_pad($profile->id, 5, '0', STR_PAD_LEFT) }}</span>
                </div>
                <h1 class="font-display text-2xl font-semibold tracking-[-0.03em] text-ink sm:text-3xl">{{ $profile->full_name }}</h1>
                <div class="font-mono text-xs text-body flex items-center gap-4">
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
                <div class="flex items-center justify-between text-xs font-mono text-body">
                    <span>POSTURE INDEX</span>
                    <span class="material-symbols-outlined text-[16px] text-accent">speed</span>
                </div>
                <div class="my-4 flex items-baseline gap-2">
                    <span class="font-mono text-4xl font-bold text-ink">{{ $scoreData['privacy_score'] ?? $profile->privacy_score }}</span>
                    <span class="font-mono text-xs text-faint">/ 100</span>
                </div>
                <div class="w-full h-1.5 bg-canvas rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#55D6E8] to-[#4ADE9B]" style="width: {{ $scoreData['privacy_score'] ?? $profile->privacy_score }}%"></div>
                </div>
                <div class="mt-3 font-mono text-[9px] text-faint leading-tight">
                    {{ $scoreData['disclaimer'] ?? 'Score is an estimate for prioritizing action.' }}
                </div>
            </div>

            <!-- Total Findings -->
            <div class="ops-card p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-mono text-body">
                    <span>INDEXED NODES</span>
                    <span class="material-symbols-outlined text-[16px] text-body">hub</span>
                </div>
                <div class="my-4 font-mono text-4xl font-bold text-ink">
                    {{ $scoreData['total_findings'] ?? 0 }}
                </div>
                <div class="font-mono text-[10px] text-body flex items-center justify-between">
                    <span>Public Accounts & Traces</span>
                    <a href="{{ route('findings') }}" class="text-accent hover:underline">View →</a>
                </div>
            </div>

            <!-- Elevated Risk Alerts -->
            <div class="ops-card p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-mono text-body">
                    <span>ELEVATED THREATS</span>
                    <span class="material-symbols-outlined text-[16px] text-danger">warning</span>
                </div>
                <div class="my-4 font-mono text-4xl font-bold text-danger">
                    {{ ($scoreData['critical_count'] ?? 0) + ($scoreData['high_count'] ?? 0) }}
                </div>
                <div class="font-mono text-[10px] text-body flex items-center justify-between">
                    <span>High-risk records</span>
                    <span class="ops-badge ops-badge-coral text-[8px]">ACTION REQ</span>
                </div>
            </div>

            <!-- Removals In-Flight -->
            <div class="ops-card p-5 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs font-mono text-body">
                    <span>OPT-OUT QUEUE</span>
                    <span class="material-symbols-outlined text-[16px] text-success">task_alt</span>
                </div>
                <div class="my-4 font-mono text-4xl font-bold text-success">
                    {{ $activeTasks->where('status', 'removed')->count() }}<span class="text-sm font-normal text-faint">/{{ $activeTasks->count() }}</span>
                </div>
                <div class="font-mono text-[10px] text-body flex items-center justify-between">
                    <span>Remediated Listings</span>
                    <a href="{{ route('removals') }}" class="text-accent hover:underline">Removals →</a>
                </div>
            </div>
        </div>

        <!-- Recent Reconnaissance Feed & Credential Alerts -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Findings Feed -->
            <div class="lg:col-span-8 ops-card p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <div class="flex items-center gap-2 font-mono text-xs font-semibold text-ink">
                        <span class="material-symbols-outlined text-accent text-[16px]">radar</span>
                        <span>RECENT DISCOVERED NODES</span>
                    </div>
                    <a href="{{ route('findings') }}" class="font-mono text-[11px] text-accent hover:underline">Full Report →</a>
                </div>

                @if($recentFindings->count())
                    <div class="divide-y divide-line">
                        @foreach($recentFindings as $f)
                            <div class="py-3 flex items-center justify-between gap-4 font-mono text-xs">
                                <div class="flex items-center gap-3">
                                    <span class="material-symbols-outlined text-[18px] 
                                        @if($f->risk === 'critical') text-danger
                                        @elseif($f->risk === 'high') text-warning
                                        @else text-accent @endif">
                                        @if($f->category === 'data_broker') corporate_fare
                                        @elseif($f->category === 'public_paste') article
                                        @else account_box @endif
                                    </span>
                                    <div>
                                        <div class="text-ink font-medium">{{ $f->source }}</div>
                                        <div class="text-[10px] text-faint">{{ $f->identifier_value }} // {{ str_replace('_', ' ', strtoupper($f->category)) }}</div>
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
                                        <a href="{{ $f->url }}" target="_blank" rel="noopener" class="text-body hover:text-ink" title="Open source">
                                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center font-mono text-xs text-faint">
                        No intelligence nodes detected. Click "Execute Recon Scan" above.
                    </div>
                @endif
            </div>

            <!-- Credential Exposures Sidepanel -->
            <div class="lg:col-span-4 ops-card p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <div class="flex items-center gap-2 font-mono text-xs font-semibold text-ink">
                        <span class="material-symbols-outlined text-warning text-[16px]">vpn_key</span>
                        <span>BREACH METADATA</span>
                    </div>
                    <span class="ops-badge ops-badge-faint text-[9px]">ZERO PASSWORDS</span>
                </div>

                @if($credentialExposures->count())
                    <div class="space-y-3 font-mono text-xs">
                        @foreach($credentialExposures as $c)
                            <div class="p-3 rounded bg-canvas border border-line space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-ink font-semibold">{{ $c->service_name }}</span>
                                    @if($c->is_resolved)
                                        <span class="text-success text-[10px]">RESOLVED</span>
                                    @else
                                        <button wire:click="markResolved({{ $c->id }})" class="ops-badge ops-badge-amber text-[9px] hover:bg-[#E9B45D]/20">
                                            Fix & Rotate
                                        </button>
                                    @endif
                                </div>
                                <div class="text-[10px] text-body">Exposed: {{ $c->email }}</div>
                                <div class="text-[9px] text-faint">Date: {{ $c->breach_date ?? 'Archived' }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    @if(config('traceless.breach.provider'))
                        <div class="py-12 text-center font-mono text-xs text-success">
                            ✓ No active credential exposures found across public disclosure lists.
                        </div>
                    @else
                        <div class="py-12 text-center font-mono text-xs leading-relaxed text-faint">
                            <span class="mb-2 block text-warning">PROVIDER NOT CONFIGURED</span>
                            Breach metadata is not enabled in this environment. TraceLess shows nothing here rather than infer or invent exposures.
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
