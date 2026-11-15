<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#080A0D">
    <title>@yield('title', 'TraceLess — Secure access')</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@500;600;700&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-[#080A0D] font-body text-[#F1F4F7] antialiased">
    <!-- Processing loop (the same footage used during a scan) as the ambient backdrop. -->
    <video
        class="pointer-events-none fixed inset-0 z-0 h-full w-full object-cover opacity-45 motion-reduce:hidden"
        autoplay muted loop playsinline preload="metadata" tabindex="-1" aria-hidden="true">
        <source src="{{ asset('assets/videos/scanner-loop.mp4') }}" type="video/mp4">
    </video>
    <!-- Readability scrims: keep form text above 4.5:1 contrast over moving footage. -->
    <div class="pointer-events-none fixed inset-0 z-0 bg-[#080A0D]/80" aria-hidden="true"></div>
    <div class="pointer-events-none fixed inset-0 z-0 bg-gradient-to-br from-[#080A0D]/70 via-[#080A0D]/30 to-[#080A0D]/85" aria-hidden="true"></div>

    <div class="relative z-10 grid min-h-screen lg:grid-cols-12">

        <!-- Intelligence briefing panel -->
        <aside class="relative hidden overflow-hidden border-r border-white/[0.08] bg-[#0A0E13]/70 lg:col-span-5 lg:flex lg:flex-col lg:backdrop-blur-md">
            <div class="pointer-events-none absolute inset-0 app-grid opacity-40" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(85,214,232,0.12),transparent_55%),radial-gradient(circle_at_90%_90%,rgba(74,222,155,0.06),transparent_50%)]" aria-hidden="true"></div>

            <div class="relative flex flex-1 flex-col p-10 xl:p-14">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-white/10 bg-[#101419] text-[#55D6E8]">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 8v4M12 16h.01" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span class="flex flex-col leading-none">
                        <span class="font-mono text-base font-bold tracking-[0.14em] text-white">TRACE<span class="text-[#55D6E8]">LESS</span></span>
                        <span class="mt-1 font-mono text-[8px] uppercase tracking-[0.22em] text-[#5D6B7A]">Confidential // Access</span>
                    </span>
                </a>

                <div class="mt-auto max-w-md">
                    <div class="inline-flex items-center gap-2 rounded border border-[#55D6E8]/25 bg-[#55D6E8]/[0.06] px-2.5 py-1 font-mono text-[9px] uppercase tracking-[0.18em] text-[#55D6E8]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#55D6E8]"></span>
                        Session enclave
                    </div>

                    <h2 class="mt-5 font-display text-[2.6rem] font-semibold leading-[1.04] tracking-[-0.03em] text-white xl:text-[3rem]">
                        A quiet command center for your public exposure.
                    </h2>
                    <p class="mt-5 max-w-sm font-body text-sm leading-relaxed text-[#A6B0BC]">
                        Public sources only. No passwords requested. Consent is recorded for every identity you choose to scan.
                    </p>

                    <div class="mt-8 space-y-3 border-t border-white/[0.08] pt-6 font-mono text-[10px] text-[#A6B0BC]">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[15px] text-[#4ADE9B]">verified_user</span>
                            <span>Consent-gated reconnaissance</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[15px] text-[#55D6E8]">dns</span>
                            <span>Evidence stored per finding</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[15px] text-[#E9B45D]">key_off</span>
                            <span>Passwordless email access</span>
                        </div>
                    </div>
                </div>

                <div class="mt-10 flex items-center justify-between border-t border-white/[0.06] pt-5 font-mono text-[9px] text-[#5D6B7A]">
                    <span>WhatsMyName CC BY-SA 4.0</span>
                    <span>user-scanner MIT</span>
                </div>
            </div>
        </aside>

        <!-- Access form -->
        <main class="relative flex items-center justify-center bg-[#080A0D]/30 px-5 py-10 sm:px-8 lg:col-span-7">
            <div class="pointer-events-none absolute inset-0 app-grid opacity-30 lg:hidden" aria-hidden="true"></div>
            <div class="relative w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-9 flex items-center gap-2.5 lg:hidden">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 bg-[#101419] text-[#55D6E8]">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="font-mono text-base font-bold tracking-[0.14em] text-white">TRACE<span class="text-[#55D6E8]">LESS</span></span>
                </a>

                @if(session('status'))
                    <div class="mb-5 rounded-lg border border-[#4ADE9B]/25 bg-[#4ADE9B]/[0.08] px-4 py-3 font-body text-sm text-[#4ADE9B]">{{ session('status') }}</div>
                @endif

                {{ $slot ?? '' }}
                @yield('content')

                <p class="mt-8 border-t border-white/[0.06] pt-5 text-center font-mono text-[10px] leading-relaxed text-[#5D6B7A]">
                    By continuing you agree to the
                    <a href="{{ route('terms') }}" class="text-[#55D6E8] hover:underline">terms</a> and
                    <a href="{{ route('privacy') }}" class="text-[#55D6E8] hover:underline">privacy policy</a>.
                </p>
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
