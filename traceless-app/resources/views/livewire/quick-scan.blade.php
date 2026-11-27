<div class="space-y-8">
    {{-- Intro --}}
    <div class="max-w-3xl">
        <div class="inline-flex items-center gap-2 rounded-full border border-line bg-surface/80 px-3 py-1 font-mono text-[10px] uppercase tracking-[0.16em] text-body">
            <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
            Free quick check · no account required
        </div>
        <h1 class="mt-5 font-display text-[clamp(2.2rem,4.8vw,4.2rem)] font-semibold leading-[0.98] tracking-[-0.055em] text-ink">
            See what the public web already knows.
        </h1>
        <p class="mt-4 max-w-2xl text-[15px] leading-relaxed text-body">
            Check one public username against <span class="font-medium text-ink">10 top public-profile sources</span>.
            The results appear right here — no signup, no password, no scan saved to an account.
        </p>
    </div>

    @if($scanError)
        <div class="rounded-xl border border-danger/30 bg-danger/[0.07] px-4 py-3 text-sm text-danger" role="alert">
            {{ $scanError }}
        </div>
    @endif

    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1.08fr)_minmax(0,0.92fr)]" style="align-items:start">
        {{-- Quick form --}}
        <section class="v-card p-5 sm:p-7" aria-labelledby="quick-scan-heading">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="v-kicker">Your public handle</p>
                    <h2 id="quick-scan-heading" class="mt-1.5 text-[1.35rem] font-semibold tracking-[-0.025em] text-ink">
                        Run the 10-source check
                    </h2>
                </div>
                <span class="v-pill hidden sm:inline-flex">~10–40 sec</span>
            </div>

            <form wire:submit="scan" class="mt-6 space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="quick-name" class="v-kicker mb-1.5 block">Your first name</label>
                        <input id="quick-name" type="text" wire:model="name" required autocomplete="given-name"
                               class="input @error('name') border-danger/60 @enderror" placeholder="Alex">
                        @error('name')<p class="mt-1 font-mono text-[10px] text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="quick-email" class="v-kicker mb-1.5 block">Email</label>
                        <input id="quick-email" type="email" wire:model="email" required autocomplete="email"
                               class="input @error('email') border-danger/60 @enderror" placeholder="you@example.com">
                        @error('email')<p class="mt-1 font-mono text-[10px] text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label for="quick-username" class="v-kicker mb-1.5 block">Public username</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 font-mono text-sm text-faint">@</span>
                        <input id="quick-username" type="text" wire:model="username" required autocomplete="username"
                               spellcheck="false" autocapitalize="off"
                               class="input !pl-8 @error('username') border-danger/60 @enderror" placeholder="yourhandle">
                    </div>
                    @error('username')<p class="mt-1 font-mono text-[10px] text-danger">{{ $message }}</p>@enderror
                    <p class="mt-1.5 text-[11px] leading-relaxed text-muted">
                        The quick check uses your <span class="text-ink">username</span> only. Name and email are used only to
                        prefill signup if you continue — nothing is saved to an account.
                    </p>
                </div>

                <div class="hidden" aria-hidden="true">
                    <label for="quick-website">Website</label>
                    <input id="quick-website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line bg-elevated/40 p-4">
                    <input type="checkbox" wire:model="consent" required
                           class="mt-0.5 rounded border-line text-accent focus:ring-accent/30">
                    <span class="text-[11px] leading-relaxed text-body">
                        I confirm this is <span class="font-medium text-ink">my own public username</span> or one I have
                        explicit permission to check, and I accept the
                        <a href="{{ route('terms') }}" class="text-accent hover:underline">terms</a> and
                        <a href="{{ route('privacy') }}" class="text-accent hover:underline">privacy policy</a>.
                    </span>
                </label>
                @error('consent')<p class="font-mono text-[10px] text-danger">{{ $message }}</p>@enderror

                <button type="submit" class="v-btn v-btn-primary w-full !justify-center !py-3.5"
                        wire:loading.attr="disabled" wire:target="scan">
                    <span wire:loading.remove wire:target="scan" class="inline-flex items-center gap-2">
                        Scan my footprint
                        <span aria-hidden="true">→</span>
                    </span>
                    <span wire:loading wire:target="scan" class="inline-flex items-center gap-2">
                        <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-canvas/40 border-t-canvas"></span>
                        Checking 10 public sources…
                    </span>
                </button>

                <div wire:loading wire:target="scan" class="rounded-lg border border-line bg-elevated/40 px-3 py-2 font-mono text-[10px] leading-relaxed text-muted">
                    We are requesting each source politely, one at a time. Some sites may be slow or block automated checks.
                </div>
            </form>
        </section>

        {{-- What this checks / limits --}}
        <aside class="space-y-4">
            <div class="v-card p-5 sm:p-6">
                <p class="v-kicker">The short list</p>
                <h2 class="mt-1.5 text-[1.1rem] font-semibold text-ink">10 public profile checks</h2>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    @foreach($checks as $check)
                        <div class="rounded-lg border border-line bg-elevated/35 px-3 py-2 font-mono text-[10px] text-body">
                            {{ $check }}
                        </div>
                    @endforeach
                </div>
                <p class="mt-4 text-[11px] leading-relaxed text-muted">
                    This is a quick look, not a full report. Each result is either a public match, no match at that source,
                    or unverified when the source refuses a check.
                </p>
            </div>

            <div class="v-card border-accent/20 p-5 sm:p-6">
                <p class="v-kicker">What a quick scan cannot do</p>
                <ul class="mt-3 space-y-2.5 text-[12px] leading-relaxed text-body">
                    <li class="flex gap-2"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-faint"></span>Email and phone-registration checks</li>
                    <li class="flex gap-2"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-faint"></span>Hundreds of deep social and developer sources</li>
                    <li class="flex gap-2"><span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-faint"></span>Removal workflow, reports and monitoring</li>
                </ul>
                <p class="mt-4 border-t border-line pt-3 text-[11px] leading-relaxed text-muted">
                    For the full picture, create a free account and run a deep scan across up to
                    <span class="text-ink">3,885 public probes</span>.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="{{ route('register') }}" class="v-btn v-btn-primary !px-3 !py-2 !text-[11px]">Deep scan with an account</a>
                    <a href="{{ route('login') }}" class="v-btn v-btn-secondary !px-3 !py-2 !text-[11px]">Sign in</a>
                </div>
            </div>
        </aside>
    </div>

    @if($scanned && $results)
        <section class="space-y-4" aria-live="polite">
            <div class="flex flex-col justify-between gap-3 border-t border-line pt-7 sm:flex-row sm:items-end">
                <div>
                    <p class="v-kicker">Quick scan complete</p>
                    <h2 class="mt-1.5 font-display text-[clamp(1.7rem,3vw,2.6rem)] font-semibold tracking-[-0.04em] text-ink">
                        {{ $results['public_matches'] }} public match{{ $results['public_matches'] === 1 ? '' : 'es' }} for <span>@</span>{{ $results['username'] }}
                    </h2>
                    <p class="mt-1 text-[12px] text-muted">
                        {{ $results['checked'] }} sources checked · {{ $results['unverified'] }} unverified · {{ $results['scanned_at'] }}
                    </p>
                </div>
                <button type="button" wire:click="resetScan" class="v-btn v-btn-secondary !px-3 !py-2 !text-[11px]">
                    Check another username
                </button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($results['sources'] as $source)
                    @php
                        $found = $source['outcome'] === 'found';
                        $unknown = in_array($source['outcome'], ['unknown', 'skipped'], true);
                        $tone = $found ? 'text-success' : ($unknown ? 'text-warning' : 'text-muted');
                        $label = $found ? 'Public profile found' : ($unknown ? 'Could not verify' : 'No public match');
                    @endphp
                    <div class="v-card flex flex-col gap-3 p-4" wire:key="quick-source-{{ $source['name'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[13px] font-medium text-ink">{{ $source['name'] }}</p>
                                <p class="mt-0.5 font-mono text-[9px] uppercase tracking-[0.13em] {{ $tone }}">{{ $label }}</p>
                            </div>
                            <span class="mt-0.5 h-2 w-2 rounded-full {{ $found ? 'bg-success' : ($unknown ? 'bg-warning' : 'bg-faint') }}"></span>
                        </div>
                        @if($found && $source['url'])
                            <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer nofollow"
                               class="break-all font-mono text-[10px] text-accent hover:underline">
                                {{ \Illuminate\Support\Str::limit($source['url'], 64) }}
                            </a>
                        @elseif($unknown && $source['reason'])
                            <p class="text-[10px] leading-relaxed text-faint">The source did not answer in a way we could verify. This is not proof of absence.</p>
                        @else
                            <p class="text-[10px] leading-relaxed text-faint">No public profile was returned at this source for this handle.</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="v-card border-accent/25 p-5 sm:p-7">
                <div class="flex flex-col items-start justify-between gap-5 sm:flex-row sm:items-center">
                    <div>
                        <p class="v-kicker">The full picture is deeper</p>
                        <h2 class="mt-1.5 text-[1.35rem] font-semibold tracking-[-0.03em] text-ink">
                            Want the full scan, evidence and monitoring?
                        </h2>
                        <p class="mt-2 max-w-xl text-[12px] leading-relaxed text-body">
                            A free account runs deep scans across social, developer and email-registration sources,
                            keeps the evidence for every match, and watches for records that reappear.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <a href="{{ route('register') }}" class="v-btn v-btn-primary">Create a free account</a>
                        <a href="{{ route('login') }}" class="v-btn v-btn-secondary">Sign in</a>
                    </div>
                </div>
                <p class="mt-4 border-t border-line pt-3 text-[10px] leading-relaxed text-faint">
                    Quick-scan results are held only in this browser session and are not saved to an account.
                    Nothing is scanned without the ownership confirmation above.
                </p>
            </div>
        </section>
    @endif
</div>
