<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#05070c">
    <title>{{ $title ?? 'Free quick scan — TraceLess' }}</title>
    <meta name="description" content="Run a free, limited public-profile check with TraceLess. See where a public username appears, then create an account for the full scan.">

    <script>
        (() => {
            const stored = localStorage.getItem('traceless-theme');
            document.documentElement.classList.toggle('dark', stored !== 'light');
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
<body class="ops-shell v-shell min-h-screen bg-canvas text-ink antialiased">
    @include('partials.page-loader')

    <video
        class="pointer-events-none fixed inset-0 z-0 h-full w-full object-cover opacity-45 motion-reduce:hidden"
        width="1920" height="1080" autoplay muted loop playsinline preload="metadata" tabindex="-1" aria-hidden="true">
        <source src="{{ asset('assets/videos/scanner-loop.mp4') }}" type="video/mp4">
    </video>
    <div class="pointer-events-none fixed inset-0 z-0 bg-canvas/85" aria-hidden="true"></div>
    <div class="pointer-events-none fixed inset-0 z-0 bg-gradient-to-br from-canvas/70 via-canvas/45 to-canvas/95" aria-hidden="true"></div>

    <div class="relative z-10 flex min-h-screen flex-col">
        <header class="border-b border-line/70 bg-canvas/60 backdrop-blur-md">
            <div class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-5 sm:px-8">
                <x-brand-logo :href="route('home')" size="sm" />
                <div class="flex items-center gap-2">
                    <a href="{{ route('login') }}" class="v-btn v-btn-secondary !px-3 !py-1.5 !text-xs">Sign in</a>
                    <a href="{{ route('register') }}" class="v-btn v-btn-primary !px-3 !py-1.5 !text-xs">Create account</a>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-5 py-9 sm:px-8 lg:py-12">
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        <footer class="border-t border-line/60 bg-canvas/55 px-5 py-4 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 font-mono text-[10px] text-faint sm:flex-row">
                <span>TraceLess · public sources only · consent required</span>
                <span>No passwords · no card during beta</span>
            </div>
        </footer>
    </div>

    @livewireScripts
</body>
</html>
