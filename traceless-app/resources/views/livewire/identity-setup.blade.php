<div class="max-w-2xl mx-auto py-8">
    <div class="ops-card p-8 space-y-6">
        <!-- Header -->
        <div class="border-b border-white/[0.08] pb-5 space-y-1">
            <div class="ops-badge ops-badge-cyan">PROVISIONING // STEP 01</div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Configure Monitored Identity</h1>
            <p class="font-mono text-xs text-[#A6B0BC]">
                TraceLess performs authorized OSINT scans strictly on publicly discoverable indices.
            </p>
        </div>

        <form wire:submit.prevent="saveAndScan" class="space-y-6 font-mono text-xs">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Legal / Full Name -->
                <div class="space-y-1.5">
                    <label class="block text-[#A6B0BC]">Target Full Name *</label>
                    <input type="text" wire:model="full_name" placeholder="e.g. Alex Vance" class="ops-input w-full">
                    @error('full_name') <span class="text-[#EF6262] text-[10px] block">{{ $message }}</span> @enderror
                </div>

                <!-- Primary Email -->
                <div class="space-y-1.5">
                    <label class="block text-[#A6B0BC]">Primary Email *</label>
                    <input type="email" wire:model="primary_email" placeholder="alex@domain.com" class="ops-input w-full">
                    @error('primary_email') <span class="text-[#EF6262] text-[10px] block">{{ $message }}</span> @enderror
                </div>

                <!-- Phone -->
                <div class="space-y-1.5">
                    <label class="block text-[#A6B0BC]">Phone (Optional)</label>
                    <input type="text" wire:model="phone_number" placeholder="+1 (555) 019-2834" class="ops-input w-full">
                </div>

                <!-- Region -->
                <div class="space-y-1.5">
                    <label class="block text-[#A6B0BC]">Region / Locality</label>
                    <input type="text" wire:model="region" placeholder="San Francisco, CA" class="ops-input w-full">
                </div>
            </div>

            <!-- Online Handles -->
            <div class="space-y-2">
                <label class="block text-[#A6B0BC]">Known Usernames & Online Handles</label>
                <div class="flex gap-2">
                    <input type="text" wire:model="username_input" wire:keydown.enter.prevent="addUsername" placeholder="e.g. alexvance, avance_dev" class="ops-input flex-1">
                    <button type="button" wire:click="addUsername" class="btn-ops-secondary py-2.5 px-4 text-xs">
                        Add Handle
                    </button>
                </div>

                <!-- Chips -->
                <div class="flex flex-wrap gap-2 pt-1">
                    @foreach($usernames as $idx => $uname)
                        <span class="ops-badge ops-badge-cyan">
                            @<span>{{ $uname }}</span>
                            <button type="button" wire:click="removeUsername({{ $idx }})" class="hover:text-[#EF6262] ml-1">&times;</button>
                        </span>
                    @endforeach
                </div>
            </div>

            <!-- Mandatory Consent Gate -->
            <div class="p-4 rounded-lg bg-[#080A0D] border border-[#E9B45D]/30 space-y-2">
                <div class="flex items-start gap-3">
                    <input type="checkbox" id="consent" wire:model="consent_confirmed" class="mt-1 rounded bg-[#101419] border-white/20 text-[#55D6E8] focus:ring-0">
                    <label for="consent" class="font-sans text-xs text-[#A6B0BC] leading-relaxed cursor-pointer">
                        <strong class="text-white font-mono">Explicit Authorization & Ownership:</strong>
                        I certify that I am the verified owner of this personal information, or have received explicit legal authorization to perform an OSINT privacy assessment on their behalf.
                    </label>
                </div>
                @error('consent_confirmed') <span class="text-[#EF6262] text-[10px] block">{{ $message }}</span> @enderror
            </div>

            <!-- Submit -->
            <div class="pt-2 flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="text-[#5D6B7A] hover:text-white">Cancel</a>
                <button type="submit" class="btn-ops-primary py-3 px-6 text-xs">
                    <span>Deploy Footprint Scan</span>
                    <span class="material-symbols-outlined text-[16px]">radar</span>
                </button>
            </div>
        </form>
    </div>
</div>
