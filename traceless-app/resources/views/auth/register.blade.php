@extends('layouts.auth')

@section('title', 'Create account — TraceLess')

@section('content')
    <div class="mb-7">
        <p class="eyebrow">Begin assessment</p>
        <h1 class="display-title mt-2 text-[2.2rem] text-ink sm:text-[2.6rem]">Create your enclave.</h1>
        <p class="mt-2 font-body text-sm leading-relaxed text-muted">Set up a private account with your email. We will send a one-time code to verify it — no password required.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-muted">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="input-base @error('name') border-coral/60 @enderror" placeholder="Alex Morgan">
            @error('name')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="mb-1.5 block font-mono text-[10px] uppercase tracking-wider text-muted">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                   class="input-base @error('email') border-coral/60 @enderror" placeholder="you@example.com">
            @error('email')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
            <p class="mt-1.5 font-body text-[11px] text-muted">This address becomes your sign-in method. Keep access to it.</p>
        </div>

        <div class="rounded-lg border border-line bg-surface/70 p-4">
            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="terms" value="1" @checked(old('terms')) required
                       class="mt-0.5 rounded border-hairline text-electric-blue focus:ring-electric-blue/30">
                <span class="font-body text-[11px] leading-relaxed text-muted">
                    I confirm I am authorised to scan this identity, will only submit public information about myself or someone with explicit permission, and accept the
                    <a href="{{ route('terms') }}" class="text-electric-blue hover:underline">terms</a> and
                    <a href="{{ route('privacy') }}" class="text-electric-blue hover:underline">privacy policy</a>.
                </span>
            </label>
            @error('terms')<p class="mt-1 font-mono text-[10px] text-coral">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="btn-primary w-full !py-3.5">
            Send verification code
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </button>
    </form>

    <div class="mt-6 rounded-lg border border-line bg-surface/70 px-4 py-3">
        <p class="flex items-center gap-2 font-mono text-[9px] uppercase tracking-wider text-muted">
            <span class="material-symbols-outlined text-[13px] text-emerald">verified_user</span>
            Verified at sign-up
        </p>
        <p class="mt-1 font-body text-[11px] leading-relaxed text-muted">The emailed code confirms your address and creates your account in one step. No password is ever stored.</p>
    </div>

    <p class="mt-6 text-center font-body text-xs text-muted">
        Already have an account? <a href="{{ route('login') }}" class="font-medium text-electric-blue hover:underline">Sign in</a>
    </p>
@endsection
