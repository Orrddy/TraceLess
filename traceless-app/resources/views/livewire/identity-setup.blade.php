<div class="mx-auto max-w-3xl space-y-6">
    <x-page-header
        eyebrow="New identity"
        title="Configure your footprint"
        description="Names, one email, a phone number and the usernames you use. Consent is recorded before anything is scanned." />

    <form wire:submit.prevent="saveAndScan" class="space-y-6">
        <!-- Names -->
        <section class="card p-6">
            <h2 class="text-sm font-semibold text-ink">Names</h2>
            <p class="mt-1 text-xs leading-relaxed text-body">
                Your current name first, then any previous names or variants you have used.
            </p>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <input type="text" wire:model="name_input" wire:keydown.enter.prevent="addName"
                       class="input flex-1" placeholder="e.g. Alex Vance" autocomplete="name" />
                <button type="button" wire:click="addName" class="btn-secondary shrink-0">Add name</button>
            </div>
            @error('name_input') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            @error('names') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror

            @if(count($names) > 0)
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach($names as $index => $name)
                        <li class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-sm text-ink">
                            {{ $name }}
                            <button type="button" wire:click="removeName({{ $index }})"
                                    class="text-faint hover:text-danger" aria-label="Remove {{ $name }}">&times;</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <!-- Contact -->
        <section class="card p-6">
            <h2 class="text-sm font-semibold text-ink">Contact</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="email" class="label mb-1.5">Email</label>
                    <input id="email" type="email" wire:model="primary_email" class="input"
                           placeholder="you@example.com" autocomplete="email" />
                    @error('primary_email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="label mb-1.5">Phone number</label>
                    <input id="phone" type="text" wire:model="phone_number" class="input"
                           placeholder="+234 801 234 5678" autocomplete="tel" />
                    <p class="mt-2 text-xs leading-relaxed text-faint">
                        Deep scans can check whether this number is registered on services. Some may send a
                        verification code, so use a number you own.
                    </p>
                    @error('phone_number') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <!-- Usernames -->
        <section class="card p-6">
            <h2 class="text-sm font-semibold text-ink">Usernames</h2>
            <p class="mt-1 text-xs leading-relaxed text-body">
                Every handle you use publicly. These are what the live scan actually checks.
            </p>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <input type="text" wire:model="username_input" wire:keydown.enter.prevent="addUsername"
                       class="input flex-1" placeholder="e.g. alexvance" autocomplete="off" spellcheck="false" />
                <button type="button" wire:click="addUsername" class="btn-secondary shrink-0">Add username</button>
            </div>
            @error('username_input') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror

            @if(count($usernames) > 0)
                <ul class="mt-3 flex flex-wrap gap-2">
                    @foreach($usernames as $index => $username)
                        <li class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 font-mono text-sm text-ink">
                            &#64;{{ $username }}
                            <button type="button" wire:click="removeUsername({{ $index }})"
                                    class="text-faint hover:text-danger" aria-label="Remove {{ $username }}">&times;</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <!-- Quick checks -->
        <section class="card p-6">
            <h2 class="text-sm font-semibold text-ink">Quick checks</h2>
            <p class="mt-1 text-xs leading-relaxed text-body">
                Optional. Both run outside the scan and neither stores a password.
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <a href="{{ route('password.check') }}" class="flex items-start gap-3 rounded-control border border-line bg-surface p-4 hover:border-accent">
                    <span class="material-symbols-outlined text-[20px] text-accent" aria-hidden="true">password</span>
                    <span>
                        <span class="block text-sm font-medium text-ink">Password checker</span>
                        <span class="mt-0.5 block text-xs leading-relaxed text-body">See if a password appeared in a known breach.</span>
                    </span>
                </a>

                <div class="rounded-control border border-line bg-surface p-4">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-[20px] text-accent" aria-hidden="true">public</span>
                        <div class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-ink">IP checker</span>
                            @if($detected_ip)
                                <p class="mt-1 font-mono text-xs text-ink">{{ $detected_ip }}</p>
                                <a href="{{ route('ip.check') }}" class="mt-1 inline-block text-xs font-medium text-accent hover:underline">See what it reveals</a>
                            @else
                                <p class="mt-0.5 text-xs leading-relaxed text-body">What the network you are on reveals.</p>
                                <button type="button" wire:click="detectIp" wire:loading.attr="disabled"
                                        class="mt-2 text-xs font-medium text-accent hover:underline">
                                    <span wire:loading.remove wire:target="detectIp">Get my IP</span>
                                    <span wire:loading wire:target="detectIp">Checking…</span>
                                </button>
                                @if($ip_error)
                                    <p class="mt-1 text-xs text-warning">{{ $ip_error }}</p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Consent -->
        <section class="card border-warning/30 p-6">
            <div class="flex items-start gap-3">
                <input type="checkbox" id="consent" wire:model="consent_confirmed"
                       class="mt-0.5 rounded border-line text-accent focus:ring-accent" />
                <label for="consent" class="text-xs leading-relaxed text-body">
                    <span class="font-medium text-ink">I own this information or have permission to check it.</span>
                    I understand TraceLess looks at public sources only, never asks for passwords, and that removal
                    cannot be guaranteed for every source.
                </label>
            </div>
            @error('consent_confirmed') <p class="mt-2 text-xs text-danger">{{ $message }}</p> @enderror
        </section>

        <div class="flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="text-sm text-body hover:text-ink">Cancel</a>
            <button type="submit" class="btn-primary">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">radar</span>
                <span>Save and scan</span>
            </button>
        </div>
    </form>
</div>
