@extends('layouts.auth')

@section('title', 'Sign in — TraceLess')

@section('content')
    <div class="mb-8">
        <p class="eyebrow">Secure enclave // access</p>
        <h1 class="display-title mt-2 text-[2.2rem] text-ink sm:text-[2.6rem]">Welcome back.</h1>
        <p class="mt-2 font-body text-sm leading-relaxed text-muted">Enter your email and we will send a 6-digit sign-in code. No password to remember or store.</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-muted">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="input-base @error('email') border-coral/60 @enderror" placeholder="you@example.com">
            @error('email')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
        </div>

        <label class="flex cursor-pointer items-center gap-2.5 py-1">
            <input type="checkbox" name="remember" value="1" class="rounded border-hairline text-electric-blue focus:ring-electric-blue/30">
            <span class="font-body text-xs text-charcoal">Keep me signed in on this device</span>
        </label>

        <button type="submit" class="btn-primary w-full !py-3.5">
            Email me a sign-in code
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
    </form>

    <div class="my-6 flex items-center gap-3">
        <span class="h-px flex-1 bg-hairline"></span>
        <span class="font-mono text-[9px] uppercase tracking-wider text-muted">New to TraceLess</span>
        <span class="h-px flex-1 bg-hairline"></span>
    </div>

    <a href="{{ route('register') }}" class="btn-secondary w-full">Create an account</a>

    <div class="mt-8 rounded-lg border border-white/[0.08] bg-[#101419]/70 px-4 py-3">
        <p class="flex items-center gap-2 font-mono text-[9px] uppercase tracking-wider text-muted">
            <span class="material-symbols-outlined text-[13px] text-emerald">lock</span>
            Passwordless by design
        </p>
        <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">Codes are single-use, expire quickly and are stored only as a hash. We never ask for your email or social passwords.</p>
    </div>
@endsection
