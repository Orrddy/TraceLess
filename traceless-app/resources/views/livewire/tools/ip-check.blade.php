<div class="space-y-7">
    <x-page-header
        eyebrow="Tools"
        title="IP check"
        description="See what the network you are on reveals: location, provider and timezone." />

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card p-6 lg:col-span-2">
            <form wire:submit="check" class="space-y-5">
                <div>
                    <label for="ip" class="label mb-1.5">IP address</label>
                    <input id="ip" type="text" wire:model="ip" inputmode="decimal" class="input font-mono"
                           autocomplete="off" spellcheck="false" placeholder="203.0.113.10" />
                    <p class="mt-2 text-xs leading-relaxed text-faint">
                        Pre-filled with the address this session is using. Local and private addresses have no public
                        record, so the check falls back to the public address this server reaches the internet from.
                    </p>
                    @error('ip') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">travel_explore</span>
                    <span wire:loading.remove wire:target="check">Run check</span>
                    <span wire:loading wire:target="check">Checking…</span>
                </button>
            </form>

            @if($error)
                <div class="mt-6 rounded-control border border-danger/30 bg-danger/[0.07] p-4" role="alert">
                    <p class="text-sm font-medium text-danger">The check could not complete</p>
                    <p class="mt-1 text-xs leading-relaxed text-body">{{ $error }}</p>
                </div>
            @endif

            @if($result)
                <div class="mt-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-line pb-3">
                        <p class="font-mono text-sm text-ink">{{ $result['ip'] }}</p>
                        <span class="badge badge-neutral">Lookup complete</span>
                    </div>

                    @if($result['auto'] ?? false)
                        <p class="text-xs leading-relaxed text-muted">
                            The address entered is local or private, so this is the public address this server reaches
                            the internet from. On your own machine that is your network; on a hosted deployment it is the
                            hosting provider's address.
                        </p>
                    @endif

                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-faint">Country</dt>
                            <dd class="mt-1 text-sm text-ink">{{ $result['country'] ?? 'Not reported' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-faint">Region</dt>
                            <dd class="mt-1 text-sm text-ink">{{ $result['region'] ?? 'Not reported' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-faint">City</dt>
                            <dd class="mt-1 text-sm text-ink">{{ $result['city'] ?? 'Not reported' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-faint">Timezone</dt>
                            <dd class="mt-1 text-sm text-ink">{{ $result['timezone'] ?? 'Not reported' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs uppercase tracking-wide text-faint">Network / provider</dt>
                            <dd class="mt-1 font-mono text-sm text-ink">{{ $result['org'] ?? 'Not reported' }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </section>

        <aside class="space-y-4">
            <div class="card p-5">
                <p class="flex items-center gap-2 text-sm font-semibold text-ink">
                    <span class="material-symbols-outlined text-[18px] text-warning" aria-hidden="true">info</span>
                    What this is not
                </p>
                <p class="mt-2 text-sm leading-relaxed text-body">
                    These are network and location signals from a public lookup. This is <span class="text-ink">not a fraud score</span>
                    and it does not say whether the address is malicious. A real risk score needs a dedicated provider with
                    its own model and key.
                </p>
            </div>

            <div class="card p-5">
                <p class="text-sm font-semibold text-ink">What it can tell you</p>
                <ul class="mt-3 space-y-2 text-sm leading-relaxed text-body">
                    <li>Approximate city and country the address is registered to.</li>
                    <li>The network or hosting provider behind it.</li>
                    <li>Whether the reported location matches where you actually are.</li>
                </ul>
            </div>

            <div class="card p-5">
                <p class="text-sm font-semibold text-ink">Privacy note</p>
                <p class="mt-2 text-sm leading-relaxed text-body">
                    Running a check sends the address to the lookup provider. TraceLess records that a lookup happened,
                    not the result.
                </p>
            </div>
        </aside>
    </div>
</div>
