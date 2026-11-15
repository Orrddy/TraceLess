<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-line">
        <div>
            <div class="ops-badge ops-badge-cyan mb-2">Exposure Telemetry</div>
            <h1 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight">Discovered Footprint Nodes</h1>
            <p class="font-mono text-xs text-body mt-1">Cross-platform correlation of publicly exposed identifiers.</p>
        </div>
    </div>

    <!-- Tactical Filter Bar -->
    <div class="ops-card p-4 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[220px]">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by source, handle, or title..."
                   class="ops-input w-full">
        </div>

        <select wire:model.live="riskFilter" class="ops-input py-2.5">
            <option value="">All Risk Tiers</option>
            <option value="critical">Critical</option>
            <option value="high">High</option>
            <option value="moderate">Moderate</option>
            <option value="low">Low</option>
        </select>

        <select wire:model.live="categoryFilter" class="ops-input py-2.5">
            <option value="">All Categories</option>
            <option value="social">Social & Media</option>
            <option value="developer">Developer & Tech</option>
            <option value="data_broker">Data Brokers</option>
            <option value="paste">Public Pastes</option>
        </select>
    </div>

    <!-- Findings Table -->
    <div class="ops-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left font-mono text-xs">
                <thead>
                    <tr class="border-b border-line bg-canvas/60 text-[10px] uppercase tracking-wider text-faint">
                        <th class="px-5 py-3.5">Source & Category</th>
                        <th class="px-5 py-3.5">Monitored Identifier</th>
                        <th class="px-5 py-3.5">Confidence</th>
                        <th class="px-5 py-3.5">Risk Tier</th>
                        <th class="px-5 py-3.5">Removal Status</th>
                        <th class="px-5 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse($findings as $f)
                        <tr class="hover:bg-surface transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-sans text-sm font-semibold text-ink">{{ $f->source }}</div>
                                <div class="text-[10px] text-faint">{{ str_replace('_', ' ', strtoupper($f->category)) }}</div>
                            </td>
                            <td class="px-5 py-4 text-body">
                                {{ $f->identifier_value }}
                            </td>
                            <td class="px-5 py-4 text-accent">
                                {{ number_format($f->confidence * 100, 0) }}%
                            </td>
                            <td class="px-5 py-4">
                                <span class="ops-badge 
                                    @if($f->risk === 'critical') ops-badge-coral
                                    @elseif($f->risk === 'high') ops-badge-amber
                                    @else ops-badge-cyan @endif">
                                    {{ strtoupper($f->risk) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-body">
                                {{ ucwords(str_replace('_', ' ', $f->status)) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if($f->url)
                                    <a href="{{ $f->url }}" target="_blank" rel="noopener" class="btn-ops-secondary py-1 px-2.5 text-[10px]">
                                        <span>Inspect</span>
                                        <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-faint">
                                No exposure nodes found matching filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="px-2">{{ $findings->links() }}</div>
</div>
