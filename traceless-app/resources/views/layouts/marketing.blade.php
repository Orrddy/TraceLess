<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#05070c">
    <title>{{ $title ?? 'TraceLess · Know what is public about you' }}</title>
    <meta name="description" content="TraceLess finds the personal information that is publicly available about you, explains the risk, and gives you a removal path for each record.">

    <script>
        (() => {
            const stored = localStorage.getItem('traceless-theme');
            document.documentElement.classList.toggle('dark', stored === null || stored === 'dark');
        })();
    </script>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas text-ink antialiased ops-shell"
      x-data="{ mobileNav: false }">

    <header class="ops-marketing-bar sticky top-0 z-50 border-b">
        <div class="mx-auto flex h-[4.5rem] max-w-[1440px] items-center justify-between px-4 sm:px-6 lg:px-10">
            <div class="flex items-center gap-8">
                <x-brand-logo :href="route('home')" />

                <nav class="hidden items-center gap-6 md:flex" aria-label="Site navigation">
                    <a href="{{ route('home') }}#how-it-works" class="marketing-nav-link"><span class="material-symbols-outlined">account_tree</span><span>How it works</span></a>
                    <a href="{{ route('home') }}#capabilities" class="marketing-nav-link"><span class="material-symbols-outlined">radar</span><span>What we check</span></a>
                    <a href="{{ route('security') }}" class="marketing-nav-link"><span class="material-symbols-outlined">shield_lock</span><span>Security</span></a>
                    <a href="{{ route('privacy') }}" class="marketing-nav-link"><span class="material-symbols-outlined">visibility_off</span><span>Privacy</span></a>
                </nav>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        class="rounded-lg border border-transparent p-2 text-body transition hover:border-line hover:bg-elevated/50 hover:text-ink"
                        aria-label="Toggle dark mode"
                        x-data="{ dark: document.documentElement.classList.contains('dark') }"
                        @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('traceless-theme', dark ? 'dark' : 'light')">
                    <span class="material-symbols-outlined text-[20px]" x-text="dark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                </button>

                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn-ghost hidden sm:inline-flex">Sign in</a>
                    <a href="{{ route('register') }}" class="btn-primary">Start a scan</a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="border-t border-line bg-surface/50 backdrop-blur-sm">
        <div class="mx-auto max-w-[1440px] px-4 py-14 sm:px-6 lg:px-10">
            <div class="grid gap-8 md:grid-cols-4">
                <div>
                    <p class="font-display text-base font-semibold text-ink">TraceLess</p>
                    <p class="mt-2 text-sm leading-relaxed text-body">
                        Find the personal information that is publicly available about you, understand the risk, and remove what can be removed.
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-faint">Product</p>
                    <ul class="mt-3 space-y-2 text-sm text-body">
                        <li><a href="{{ route('home') }}#how-it-works" class="hover:text-ink">How it works</a></li>
                        <li><a href="{{ route('home') }}#capabilities" class="hover:text-ink">What we check</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-ink">Start a scan</a></li>
                    </ul>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-faint">Trust</p>
                    <ul class="mt-3 space-y-2 text-sm text-body">
                        <li><a href="{{ route('security') }}" class="hover:text-ink">Security</a></li>
                        <li><a href="{{ route('privacy') }}" class="hover:text-ink">Privacy policy</a></li>
                        <li><a href="{{ route('terms') }}" class="hover:text-ink">Terms</a></li>
                    </ul>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-faint">Attribution</p>
                    <p class="mt-3 text-sm leading-relaxed text-body">
                        Public-source discovery uses
                        <a href="https://github.com/WebBreacher/WhatsMyName" target="_blank" rel="noopener" class="text-accent hover:underline">WhatsMyName</a>
                        (CC BY-SA 4.0) and
                        <a href="https://github.com/kaifcodec/user-scanner" target="_blank" rel="noopener" class="text-accent hover:underline">user-scanner</a>
                        (MIT).
                    </p>
                </div>
            </div>

            <div class="mt-10 flex flex-col items-start justify-between gap-3 border-t border-line pt-6 text-xs text-faint sm:flex-row sm:items-center">
                <span>&copy; {{ date('Y') }} TraceLess</span>
                <span>Removal is not guaranteed. Scores are prioritisation aids, not statements of fact.</span>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
