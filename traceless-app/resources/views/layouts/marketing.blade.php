<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#ffffff">
    <title>{{ $title ?? 'TraceLess · Know what is public about you' }}</title>
    <meta name="description" content="TraceLess finds the personal information that is publicly available about you, explains the risk, and gives you a removal path for each record.">

    <script>
        (() => {
            const stored = localStorage.getItem('traceless-theme');
            document.documentElement.classList.toggle('dark', stored === 'dark');
        })();
    </script>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Schibsted+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas text-ink antialiased"
      x-data="{ mobileNav: false }">

    <header class="sticky top-0 z-50 border-b border-line bg-canvas/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <div class="flex items-center gap-8">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent text-white">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="font-display text-lg font-semibold tracking-tight text-ink">TraceLess</span>
                </a>

                <nav class="hidden items-center gap-6 text-sm text-body md:flex" aria-label="Site navigation">
                    <a href="{{ route('home') }}#how-it-works" class="hover:text-ink">How it works</a>
                    <a href="{{ route('home') }}#capabilities" class="hover:text-ink">What we check</a>
                    <a href="{{ route('security') }}" class="hover:text-ink">Security</a>
                    <a href="{{ route('privacy') }}" class="hover:text-ink">Privacy</a>
                </nav>
            </div>

            <div class="flex items-center gap-2">
                <button type="button"
                        class="rounded-lg p-2 text-body hover:bg-surface"
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

    <footer class="border-t border-line bg-surface">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
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
