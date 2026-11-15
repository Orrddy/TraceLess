@extends('layouts.auth')

@section('title', 'Enter your code — TraceLess')

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

    @if($demoCode)
        <div class="mb-5 rounded-lg border border-[#E9B45D]/30 bg-[#E9B45D]/[0.07] px-4 py-3">
            <p class="flex items-center gap-2 font-mono text-[9px] uppercase tracking-wider text-[#E9B45D]">
                <span class="material-symbols-outlined text-[13px]">science</span>
                Demo mode — code shown locally
            </p>
            <p class="mt-1 font-mono text-2xl font-semibold tracking-[0.3em] text-ink">{{ $demoCode }}</p>
            <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">This panel is only rendered while demo mode is enabled. Production sends the code by email and never displays it.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('auth.verify.submit') }}" class="space-y-4">
        @csrf

        <div>
            <label for="code" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-muted">One-time code</label>
            <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
                   autocomplete="one-time-code" placeholder="000000"
                   class="input-base text-center font-mono text-2xl tracking-[0.5em] @error('code') border-coral/60 @enderror">
            @error('code')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn-primary w-full !py-3.5">
            {{ $isRegistration ? 'Verify and create account' : 'Verify and sign in' }}
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
    </form>

    <form method="POST" action="{{ route('auth.verify.resend') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn-secondary w-full" @if($resendAfter > 0) disabled @endif>
            {{ $resendAfter > 0 ? "Resend available in {$resendAfter}s" : 'Send a new code' }}
        </button>
    </form>

    <div class="mt-6 flex items-center justify-between gap-3">
        <a href="{{ $isRegistration ? route('register') : route('login') }}" class="font-mono text-[10px] uppercase tracking-wider text-electric-blue hover:underline">
            {{ $isRegistration ? 'Edit details' : 'Use another email' }}
        </a>
        <span class="font-mono text-[9px] uppercase tracking-wider text-muted">Expires in {{ config('traceless.otp.expiry_minutes', 10) }} min</span>
    </div>

    <div class="mt-8 rounded-lg border border-white/[0.08] bg-[#101419]/70 px-4 py-3">
        <p class="flex items-center gap-2 font-mono text-[9px] uppercase tracking-wider text-muted">
            <span class="material-symbols-outlined text-[13px] text-emerald">lock</span>
            Single use · hashed at rest
        </p>
        <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">The last code issued is the only valid one. After {{ config('traceless.otp.max_attempts', 5) }} incorrect attempts you must request a new code.</p>
    </div>
@endsection
