@extends('layouts.app')

@section('title', 'Password check · TraceLess')

@section('content')
    <div class="space-y-7">
        <x-page-header
            eyebrow="Tools"
            title="Password check"
            description="See whether a password has appeared in a known public breach. It never leaves this device." />

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="card p-6 lg:col-span-2" x-data="passwordChecker()">
                <form @submit.prevent="check()" class="space-y-5">
                    <div>
                        <label for="password" class="label mb-1.5">Password to check</label>
                        <input id="password" type="password" x-model="password" autocomplete="off" spellcheck="false"
                               class="input" placeholder="Type the password you want to check" />
                        <p class="mt-2 text-xs leading-relaxed text-faint">
                            The browser hashes it locally and sends only the first five characters of that hash to
                            <span class="text-body">api.pwnedpasswords.com</span>. The password itself is never sent, logged or stored.
                        </p>
                    </div>

                    <button type="submit" class="btn-primary" x-bind:disabled="busy || password.length === 0">
                        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">shield_lock</span>
                        <span x-show="!busy">Check password</span>
                        <span x-show="busy" x-cloak>Checking…</span>
                    </button>
                </form>

                <div aria-live="polite" class="mt-6 space-y-3">
                    <template x-if="error">
                        <div class="rounded-control border border-danger/30 bg-danger/[0.07] p-4">
                            <p class="text-sm font-medium text-danger">The check could not complete</p>
                            <p class="mt-1 text-xs leading-relaxed text-body" x-text="error"></p>
                        </div>
                    </template>

                    <template x-if="result && result.count > 0">
                        <div class="rounded-control border border-danger/35 bg-danger/[0.06] p-5">
                            <p class="text-sm font-semibold text-danger">
                                Found in <span x-text="result.count.toLocaleString()"></span> known breaches
                            </p>
                            <p class="mt-2 text-sm leading-relaxed text-body">
                                This password appears in public breach data. Change it wherever it is used, starting with
                                email, banking and anything that holds payment details. Turn on two-factor authentication
                                where you can.
                            </p>
                        </div>
                    </template>

                    <template x-if="result && result.count === 0">
                        <div class="rounded-control border border-success/30 bg-success/[0.06] p-5">
                            <p class="text-sm font-semibold text-success">Not found in the breach dataset</p>
                            <p class="mt-2 text-sm leading-relaxed text-body">
                                That is not a clean bill of health. The dataset only contains breaches that have been
                                published, so a password can still be weak or reused. Use a password manager and a unique
                                password per account.
                            </p>
                        </div>
                    </template>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="card p-5">
                    <p class="text-sm font-semibold text-ink">How the check works</p>
                    <ol class="mt-3 space-y-2 text-sm leading-relaxed text-body">
                        <li>1. Your browser computes the SHA-1 hash of the password.</li>
                        <li>2. It sends only the first five characters of that hash.</li>
                        <li>3. The service returns every hash suffix that shares the prefix.</li>
                        <li>4. The comparison happens here, on your device.</li>
                    </ol>
                </div>

                <div class="card p-5">
                    <p class="flex items-center gap-2 text-sm font-semibold text-ink">
                        <span class="material-symbols-outlined text-[18px] text-warning" aria-hidden="true">info</span>
                        What this cannot tell you
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-body">
                        It cannot tell you which account was breached, whether the password is still in use, or whether
                        your own account was part of an incident. For that, check the address itself against a breach
                        notification service.
                    </p>
                </div>
            </aside>
        </div>
    </div>

    <script>
        function passwordChecker() {
            return {
                password: '',
                busy: false,
                result: null,
                error: null,

                async check() {
                    if (!this.password || this.busy) return;

                    this.busy = true;
                    this.error = null;
                    this.result = null;

                    try {
                        if (!window.crypto || !window.crypto.subtle) {
                            throw new Error('This browser does not expose local hashing on this connection. Use HTTPS or localhost.');
                        }

                        const bytes = new TextEncoder().encode(this.password);
                        const digest = await window.crypto.subtle.digest('SHA-1', bytes);
                        const hex = Array.from(new Uint8Array(digest))
                            .map((byte) => byte.toString(16).padStart(2, '0'))
                            .join('')
                            .toUpperCase();

                        const prefix = hex.slice(0, 5);
                        const suffix = hex.slice(5);

                        const response = await fetch('https://api.pwnedpasswords.com/range/' + prefix);

                        if (!response.ok) {
                            throw new Error('The service replied with status ' + response.status + '. Try again in a moment.');
                        }

                        const body = await response.text();                        const match = body
                            .split('\n')
                            .map((line) => line.trim().split(':'))
                            .find((parts) => parts[0] === suffix);

                        this.result = { count: match ? parseInt(match[1], 10) : 0 };
                        this.password = '';
                    } catch (failure) {
                        this.error = failure.message || 'The check could not complete.';
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
    </script>
@endsection
