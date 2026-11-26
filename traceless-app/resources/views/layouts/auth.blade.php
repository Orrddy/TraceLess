<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#05070c">
    <title>@yield('title', 'TraceLess · Secure access')</title>

    <script>
        (() => {
            const stored = localStorage.getItem('traceless-theme');
            document.documentElement.classList.toggle('dark', stored === 'dark');
        })();
    </script>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@500;600;700&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    @include('partials.page-loader-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas font-body text-ink antialiased">
    @include('partials.page-loader')
    <!-- Processing loop (the same footage used during a scan) as the ambient backdrop. -->
    <video
        class="pointer-events-none fixed inset-0 z-0 h-full w-full object-cover opacity-45 motion-reduce:hidden"
        width="1920" height="1080" autoplay muted loop playsinline preload="metadata" tabindex="-1" aria-hidden="true">
        <source src="{{ asset('assets/videos/scanner-loop.mp4') }}" type="video/mp4">
    </video>
    <!-- Readability scrims: keep form text above 4.5:1 contrast over moving footage. -->
    <div class="pointer-events-none fixed inset-0 z-0 bg-canvas/80" aria-hidden="true"></div>
    <div class="pointer-events-none fixed inset-0 z-0 bg-gradient-to-br from-canvas/70 via-canvas/40 to-canvas/90" aria-hidden="true"></div>

    <div class="relative z-10 grid min-h-screen lg:grid-cols-12">

        <!-- Intelligence briefing panel -->
        <aside class="relative hidden overflow-hidden border-r border-line bg-surface/70 lg:col-span-5 lg:flex lg:flex-col lg:backdrop-blur-md">
            <div class="pointer-events-none absolute inset-0 app-grid opacity-40" aria-hidden="true"></div>
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(85,214,232,0.12),transparent_55%),radial-gradient(circle_at_90%_90%,rgba(74,222,155,0.06),transparent_50%)]" aria-hidden="true"></div>

            <div class="relative flex flex-1 flex-col p-10 xl:p-14">
                <x-brand-logo :href="route('home')" size="lg" tagline="Account access" />

                <div class="mt-auto max-w-md">
                    <div class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium text-body">
                        <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                        Secure sign-in
                    </div>

                    <h2 class="mt-5 font-display text-[2.4rem] font-semibold leading-[1.1] tracking-[-0.02em] text-ink xl:text-[2.8rem]">
                        Know what is public about you.
                    </h2>
                    <p class="mt-5 max-w-sm text-sm leading-relaxed text-body">
                        We check public sources only, record your consent for every identity, and never ask for a password.
                    </p>

                    <div class="mt-8 space-y-3 border-t border-line pt-6 text-sm text-body">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[15px] text-success">verified_user</span>
                            <span>Consent recorded for every scan</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[15px] text-accent">dns</span>
                            <span>Evidence stored per finding</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-[15px] text-warning">key_off</span>
                            <span>No passwords, ever</span>
                        </div>
                    </div>
                </div>

                <div class="mt-10 flex items-center justify-between border-t border-line pt-5 text-xs text-faint">
                    <span>Discovery: WhatsMyName (CC BY-SA 4.0)</span>
                    <span>user-scanner (MIT)</span>
                </div>
            </div>
        </aside>

        <!-- Access form -->
        <main class="relative flex items-center justify-center bg-canvas/30 px-5 py-10 sm:px-8 lg:col-span-7">
            <div class="pointer-events-none absolute inset-0 app-grid opacity-30 lg:hidden" aria-hidden="true"></div>
            <div class="relative w-full max-w-md">
                <x-brand-logo :href="route('home')" size="sm" class="mb-9 lg:hidden" />

                @if(session('status'))
                    <div class="mb-5 rounded-lg border border-[#4ADE9B]/25 bg-success/[0.08] px-4 py-3 font-body text-sm text-success">{{ session('status') }}</div>
                @endif

                {{ $slot ?? '' }}
                @yield('content')

                <p class="mt-8 border-t border-line pt-5 text-center font-mono text-[10px] leading-relaxed text-faint">
                    By continuing you agree to the
                    <a href="{{ route('terms') }}" class="text-accent hover:underline">terms</a> and
                    <a href="{{ route('privacy') }}" class="text-accent hover:underline">privacy policy</a>.
                </p>
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
