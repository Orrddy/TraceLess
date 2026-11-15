<div class="space-y-6">
    <x-page-header eyebrow="Identity ledger" title="My identity" description="Manage the public identifiers TraceLess is authorized to check. Consent is recorded per profile.">
        <a href="{{ route('identity.create') }}" class="btn-primary"><span class="material-symbols-outlined text-[16px]">add</span> New identity</a>
    </x-page-header>

    @if(session('status'))
        <div class="rounded-xl border border-emerald/25 bg-emerald/10 px-4 py-3 font-body text-sm text-[#0d7a56]">{{ session('status') }}</div>
    @endif

    @forelse($profiles as $profile)
        <div class="glass-card overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-hairline bg-white/30 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-hairline bg-white font-mono text-sm font-semibold text-electric-blue">
                        {{ $profile->privacy_score }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-editorial text-2xl text-ink">{{ $profile->full_name }}</h2>
                            <span class="badge {{ $profile->consent_acknowledged ? 'badge-emerald' : 'badge-moderate' }}">
                                {{ $profile->consent_acknowledged ? 'Consent recorded' : 'Consent missing' }}
                            </span>
                        </div>
                        <p class="mt-0.5 font-mono text-[10px] text-muted">
                            {{ $profile->primary_email }} · {{ $profile->region ?: 'No region' }} · Created {{ $profile->created_at->format('M d, Y') }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button wire:click="regenerateScore({{ $profile->id }})" class="btn-secondary !px-3 !py-2 !text-[10px]">
                        <span class="material-symbols-outlined text-[14px]">refresh</span> Recalculate
                    </button>
                    <button wire:click="edit({{ $profile->id }})" class="btn-secondary !px-3 !py-2 !text-[10px]">
                        <span class="material-symbols-outlined text-[14px]">edit</span> Edit
                    </button>
                    <a href="{{ route('scans.create') }}" class="btn-primary !px-3 !py-2 !text-[10px]">Scan now</a>
                </div>
            </div>

            <div class="grid gap-6 px-5 py-5 sm:px-6 lg:grid-cols-12">
                <div class="lg:col-span-8">
                    <div class="flex items-center justify-between">
                        <p class="mono-label">Monitored identifiers</p>
                        <span class="font-mono text-[10px] text-muted">{{ $profile->publicIdentifiers->count() }} total</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse($profile->publicIdentifiers as $identifier)
                            <span class="group inline-flex items-center gap-1.5 rounded-lg border border-hairline bg-white/70 px-2.5 py-1.5 font-mono text-[10px] text-charcoal">
                                <span class="uppercase tracking-wider text-muted">{{ $identifier->type }}</span>
                                <span class="max-w-[200px] truncate">{{ $identifier->value }}</span>
                                <button wire:click="removeIdentifier({{ $identifier->id }})" wire:confirm="Remove this identifier from future scans?" class="text-muted/60 hover:text-coral" aria-label="Remove identifier">&times;</button>
                            </span>
                        @empty
                            <span class="font-mono text-[10px] text-muted">No identifiers stored.</span>
                        @endforelse
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach([
                            ['Findings', $profile->findings()->count(), 'text-ink'],
                            ['High risk', $profile->findings()->whereIn('risk', ['high', 'critical'])->count(), 'text-coral'],
                            ['Removals', $profile->removalTasks()->count(), 'text-electric-blue'],
                            ['Leaks', $profile->credentialExposures()->where('is_resolved', false)->count(), 'text-amber'],
                        ] as [$label, $value, $tone])
                            <div class="rounded-xl border border-hairline bg-white/50 p-2.5">
                                <p class="font-mono text-lg font-semibold {{ $tone }}">{{ $value }}</p>
                                <p class="font-mono text-[8px] uppercase tracking-wider text-muted">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <div class="rounded-2xl border border-hairline bg-white/50 p-4">
                        <p class="mono-label">Consent record</p>
                        @if($profile->consent_given_at)
                            <p class="mt-2 font-body text-xs leading-relaxed text-charcoal">Owner authorization recorded {{ $profile->consent_given_at->format('M d, Y H:i') }} UTC.</p>
                            <p class="mt-2 font-mono text-[9px] leading-relaxed text-muted">Audit trail retained. Consent can be revoked by deleting the profile data.</p>
                        @else
                            <p class="mt-2 font-body text-xs text-coral">No consent event on record. Scans are blocked until consent is reconfirmed.</p>
                        @endif
                        <div class="mt-3 flex items-center justify-between border-t border-hairline pt-3">
                            <span class="font-mono text-[9px] uppercase tracking-wider text-muted">Last scan</span>
                            <span class="font-mono text-[10px] text-ink">{{ $profile->latestScan()?->created_at?->diffForHumans() ?? 'Never' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if($editingId === $profile->id)
                <div class="border-t border-hairline bg-canvas-alt/60 px-5 py-5 sm:px-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mono-label mb-1.5 block">Region</label>
                            <input type="text" wire:model="region" class="input-base">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mono-label mb-1.5 block">Aliases</label>
                            <input type="text" wire:model="aliases" class="input-base">
                        </div>
                        <div>
                            <label class="mono-label mb-1.5 block">Add identifier</label>
                            <select wire:model="new_type" class="select-base w-full">
                                <option value="username">Username</option>
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                                <option value="url">Public URL</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mono-label mb-1.5 block">Value</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="new_identifier" wire:keydown.enter.prevent="addIdentifier" class="input-base" placeholder="Handle, email, phone or URL">
                                <button wire:click="addIdentifier" class="btn-secondary shrink-0">Add</button>
                            </div>
                            @error('new_identifier') <p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end gap-2">
                        <button wire:click="$set('editingId', null)" class="btn-ghost">Cancel</button>
                        <button wire:click="save" class="btn-primary">Save changes</button>
                    </div>
                </div>
            @endif
        </div>
    @empty
        <x-empty-state icon="fingerprint" title="No identity profiles yet" description="Create a profile to scan public sources. You must confirm ownership or permission before any scan can run.">
            <a href="{{ route('identity.create') }}" class="btn-primary">Create identity profile</a>
        </x-empty-state>
    @endforelse
</div>
