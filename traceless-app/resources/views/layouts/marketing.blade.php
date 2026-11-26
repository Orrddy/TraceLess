<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FAFAFA">
    <title>{{ $title ?? 'TraceLess · Know what is public about you' }}</title>
    <meta name="description" content="TraceLess finds the personal information that is publicly available about you, explains the risk, and gives you a removal path for each record.">

    <script>
        // Light by default; the toggle below persists an explicit choice.
        (() => {
            const stored = localStorage.getItem('traceless-theme');
            const dark = stored === 'dark';
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    @include('partials.page-loader-head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas text-ink antialiased ops-shell v-shell" x-data="{ mobileNav: false }">

    @include('partials.page-loader')

    <header class="sticky top-0 z-50 border-b border-line/70 bg-canvas/85 backdrop-blur-md">
        <div class="mx-auto flex h-16 max-w-[1200px] items-center justify-between gap-4 px-5 sm:px-8">
            <div class="flex items-center gap-8">
                <x-brand-logo :href="route('home')" size="sm" :spin="true" />

                <nav class="hidden items-center gap-1 md:flex" aria-label="Site navigation">
                    <a href="{{ route('home') }}#capabilities" class="m-nav-link">What we check</a>
                    <a href="{{ route('home') }}#how-it-works" class="m-nav-link">How it works</a>
                    <a href="{{ route('security') }}" class="m-nav-link">Security</a>
                    <a href="{{ route('privacy') }}" class="m-nav-link">Privacy</a>
                </nav>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        class="v-btn v-btn-ghost h-9 w-9 !px-0"
                        aria-label="Toggle dark mode"
                        x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        x-on:click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('traceless-theme', dark ? 'dark' : 'light')">
                    <span class="material-symbols-outlined text-[19px]" x-text="dark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                </button>

                <span class="v-pill hidden md:inline-flex">v1.0 · Beta</span>

                @auth
                    <a href="{{ route('dashboard') }}" class="v-btn v-btn-primary">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="v-btn v-btn-ghost hidden sm:inline-flex">Sign in</a>
                    <a href="{{ route('register') }}" class="v-btn v-btn-primary">Start free</a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="border-t border-line/70">
        <div class="mx-auto max-w-[1200px] px-5 py-14 sm:px-8">
            <div class="grid gap-10 md:grid-cols-4">
                <div class="md:col-span-1">
                    <x-brand-logo :href="route('home')" size="sm" :spin="true" />
                    <p class="mt-3 max-w-xs text-[13px] leading-relaxed text-body">
                        Public-source privacy monitoring. Find what is already public about you and understand the risk.
                    </p>
                    <p class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="v-pill">v1.0 · Beta</span>
                        <span class="v-pill">v2.0 coming</span>
                    </p>
                </div>

                <div>
                    <p class="v-kicker">Product</p>
                    <ul class="mt-3 space-y-2 text-[13px] text-body">
                        <li><a href="{{ route('home') }}#capabilities" class="hover:text-ink">What we check</a></li>
                        <li><a href="{{ route('home') }}#how-it-works" class="hover:text-ink">How it works</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-ink">Start free</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-ink">Sign in</a></li>
                    </ul>
                </div>

                <div>
                    <p class="v-kicker">Tools</p>
                    <ul class="mt-3 space-y-2 text-[13px] text-body">
                        <li><a href="{{ route('password.check') }}" class="hover:text-ink">Password exposure check</a></li>
                        <li><a href="{{ route('ip.check') }}" class="hover:text-ink">IP intelligence</a></li>
                        <li><a href="{{ route('security') }}" class="hover:text-ink">Security</a></li>
                    </ul>
                </div>

                <div>
                    <p class="v-kicker">Trust</p>
                    <ul class="mt-3 space-y-2 text-[13px] text-body">
                        <li><a href="{{ route('privacy') }}" class="hover:text-ink">Privacy policy</a></li>
                        <li><a href="{{ route('terms') }}" class="hover:text-ink">Terms</a></li>
                        <li><a href="{{ route('security') }}" class="hover:text-ink">How data is handled</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-col items-start justify-between gap-3 border-t border-line/70 pt-6 text-[12px] text-muted sm:flex-row sm:items-center">
                <span>&copy; {{ date('Y') }} TraceLess · public-source privacy monitoring</span>
                <span>Attribution: WhatsMyName (CC BY-SA 4.0), user-scanner (MIT). Scores are prioritisation aids, not statements of fact.</span>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
