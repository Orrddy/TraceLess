@extends('layouts.auth')

@section('title', 'Enter your code · TraceLess')

@section('content')
    <div class="mb-7">
        <p class="eyebrow">{{ $isRegistration ? 'Verify // create account' : 'Verify // secure access' }}</p>
        <h1 class="display-title mt-2 text-[2.2rem] text-ink sm:text-[2.6rem]">Check your inbox.</h1>
        <p class="mt-2 font-body text-sm leading-relaxed text-muted">
            We sent a 6-digit {{ $isRegistration ? 'registration' : 'sign-in' }} code to
            <span class="font-medium text-ink">{{ $maskedEmail }}</span>.
            Enter it below to continue.
        </p>
    </div>

    <form method="POST" action="{{ route('auth.verify.submit') }}" class="space-y-4"
          x-data="{ submitting: false }" x-ref="verifyForm">
        @csrf

        <div>
            <label for="code" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-muted">One-time code</label>
            <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
                   autocomplete="one-time-code" placeholder="000000"
                   x-on:input="
                       const digits = $event.target.value.replace(/\D/g, '').slice(0, 6);
                       if ($event.target.value !== digits) { $event.target.value = digits; }
                       if (digits.length === 6 && !submitting) {
                           submitting = true;
                           $refs.verifyForm.requestSubmit();
                       }
                   "
                   class="input-base text-center font-mono text-2xl tracking-[0.5em] @error('code') border-coral/60 @enderror">
            @error('code')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn-primary w-full !py-3.5" x-bind:disabled="submitting">
            <span x-show="!submitting">{{ $isRegistration ? 'Verify and create account' : 'Verify and sign in' }}</span>
            <span x-show="submitting" x-cloak>Verifying…</span>
            <span class="material-symbols-outlined text-[16px]" x-show="!submitting">arrow_forward</span>
        </button>
    </form>

    <form method="POST" action="{{ route('auth.verify.resend') }}" class="mt-3"
          x-data="{
              left: {{ (int) $resendAfter }},
              tick: null,
              start() {
                  if (this.left <= 0) { return; }
                  this.tick = setInterval(() => {
                      this.left = Math.max(0, this.left - 1);
                      if (this.left === 0) { clearInterval(this.tick); }
                  }, 1000);
              }
          }"
          x-init="start()">
        @csrf
        <button type="submit" class="btn-secondary w-full" x-bind:disabled="left > 0">
            <span x-show="left > 0">Resend available in <span x-text="left"></span>s</span>
            <span x-show="left === 0" x-cloak>Send a new code</span>
        </button>
    </form>

    <div class="mt-6 flex items-center justify-between gap-3">
        <a href="{{ $isRegistration ? route('register') : route('login') }}" class="font-mono text-[10px] uppercase tracking-wider text-electric-blue hover:underline">
            {{ $isRegistration ? 'Edit details' : 'Use another email' }}
        </a>
        <span class="font-mono text-[9px] uppercase tracking-wider text-muted">Expires in {{ config('traceless.otp.expiry_minutes', 10) }} min</span>
    </div>

    <div class="mt-8 rounded-lg border border-line bg-surface/70 px-4 py-3">
        <p class="flex items-center gap-2 font-mono text-[9px] uppercase tracking-wider text-muted">
            <span class="material-symbols-outlined text-[13px] text-emerald">lock</span>
            Single use · hashed at rest
        </p>
        <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">The last code issued is the only valid one. After {{ config('traceless.otp.max_attempts', 5) }} incorrect attempts you must request a new code.</p>
    </div>
@endsection
