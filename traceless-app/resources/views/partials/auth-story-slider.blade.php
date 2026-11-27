{{-- Auth story carousel: five rotating notes beside the sign-in form. --}}
<div
    x-data="{
        slide: 0,
        count: 5,
        timer: null,
        start() {
            this.stop();
            this.timer = setInterval(() => { this.slide = (this.slide + 1) % this.count; }, 6500);
        },
        stop() {
            if (this.timer) { clearInterval(this.timer); this.timer = null; }
        },
        go(n) { this.slide = n; this.start(); }
    }"
    x-init="start()"
    @mouseenter="stop()"
    @mouseleave="start()"
    class="relative"
    aria-roledescription="carousel"
    aria-label="What TraceLess looks for"
>
    <div class="flex items-center justify-between gap-4">
        <span class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium text-body">
            <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
            Secure sign-in
        </span>
        <span class="font-mono text-[10px] tracking-[0.14em] text-faint">
            <span x-text="String(slide + 1).padStart(2, '0')"></span> / 05
        </span>
    </div>

    <div class="relative mt-6 min-h-[280px]" aria-live="polite">
        <div x-show="slide === 0" x-cloak x-transition.opacity.duration.400ms>
            <p class="v-kicker text-accent">What could be found</p>
            <h2 class="mt-3 font-display text-[2.1rem] font-semibold leading-[1.05] tracking-[-0.045em] text-ink xl:text-[2.45rem]">
                A public footprint you never chose.
            </h2>
            <p class="mt-4 text-sm leading-relaxed text-body">
                Old profiles, people-search listings and contact details can stay discoverable long after you have moved on.
            </p>
        </div>

        <div x-show="slide === 1" x-cloak x-transition.opacity.duration.400ms>
            <p class="v-kicker text-warning">What hackers can do</p>
            <h2 class="mt-3 font-display text-[2.1rem] font-semibold leading-[1.05] tracking-[-0.045em] text-ink xl:text-[2.45rem]">
                Public details make attacks believable.
            </h2>
            <p class="mt-4 text-sm leading-relaxed text-body">
                A real name, role and contact history help attackers make phishing, impersonation and account-recovery messages feel personal.
            </p>
        </div>

        <div x-show="slide === 2" x-cloak x-transition.opacity.duration.400ms>
            <p class="v-kicker text-[#4ADE9B]">A cleaner start</p>
            <h2 class="mt-3 font-display text-[2.1rem] font-semibold leading-[1.05] tracking-[-0.045em] text-ink xl:text-[2.45rem]">
                Start 2027 with a new self.
            </h2>
            <p class="mt-4 text-sm leading-relaxed text-body">
                Review what is public, remove what should not be there, and step into the new year with less of your life on display.
            </p>
        </div>

        <div x-show="slide === 3" x-cloak x-transition.opacity.duration.400ms>
            <p class="v-kicker text-accent">Give it time</p>
            <h2 class="mt-3 font-display text-[2.1rem] font-semibold leading-[1.05] tracking-[-0.045em] text-ink xl:text-[2.45rem]">
                A deep scan is not instant.
            </h2>
            <p class="mt-4 text-sm leading-relaxed text-body">
                It can take up to two hours. That depends on how long you have been online and how much of you is public — so we give it the room to be thorough.
            </p>
        </div>

        <div x-show="slide === 4" x-cloak x-transition.opacity.duration.400ms>
            <p class="v-kicker text-accent">Crawlers at work</p>
            <h2 class="mt-3 font-display text-[2.1rem] font-semibold leading-[1.05] tracking-[-0.045em] text-ink xl:text-[2.45rem]">
                We work across the public web.
            </h2>
            <p class="mt-4 text-sm leading-relaxed text-body">
                Our crawlers check public sources and record the evidence behind every match. No passwords, no private accounts, no guesses.
            </p>
        </div>
    </div>

    <div class="mt-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-1.5" role="tablist" aria-label="Choose a slide">
            <template x-for="i in count" :key="i">
                <button type="button"
                        @click="go(i - 1)"
                        :aria-label="'Show slide ' + i"
                        :aria-selected="slide === i - 1"
                        class="h-1.5 rounded-full transition-all duration-300"
                        :class="slide === i - 1 ? 'w-7 bg-ink' : 'w-1.5 bg-ink/25 hover:bg-ink/50'"></button>
            </template>
        </div>
        <div class="flex items-center gap-1">
            <button type="button" @click="go((slide + count - 1) % count)" aria-label="Previous slide"
                    class="flex h-8 w-8 items-center justify-center rounded-full border border-line text-body transition hover:border-accent/50 hover:text-ink">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
            </button>
            <button type="button" @click="go((slide + 1) % count)" aria-label="Next slide"
                    class="flex h-8 w-8 items-center justify-center rounded-full border border-line text-body transition hover:border-accent/50 hover:text-ink">
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </button>
        </div>
    </div>

    <div class="mt-7 space-y-2.5 border-t border-line pt-5 text-[12px] text-body">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[15px] text-success">verified_user</span>
            <span>Consent recorded for every scan</span>
        </div>
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[15px] text-accent">dns</span>
            <span>Evidence stored per match</span>
        </div>
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[15px] text-warning">key_off</span>
            <span>No passwords, ever</span>
        </div>
    </div>
</div>
