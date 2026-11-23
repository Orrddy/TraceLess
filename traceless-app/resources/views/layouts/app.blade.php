<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#05070c">
    <title>{{ $title ?? 'TraceLess · Privacy dashboard' }}</title>
    <meta name="description" content="TraceLess privacy dashboard: your public exposure, credential risk and removal progress in one place.">

    <script>
        // Resolve the theme before paint so there is no flash.
        (() => {
            const stored = localStorage.getItem('traceless-theme');
            const dark = stored ? stored === 'dark' : true;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="app-grid min-h-screen bg-canvas text-ink antialiased ops-shell"
      x-data="{ mobileNav: false, toast: false, toastMsg: '' }"
      @notify.window="toastMsg = $event.detail.message || 'Saved'; toast = true; setTimeout(() => toast = false, 4000)"
      @toast.window="toastMsg = $event.detail.message || 'Saved'; toast = true; setTimeout(() => toast = false, 4000)">

    <!-- Sidebar -->
    <aside class="ops-sidebar fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-line lg:flex">
        <div class="flex h-[4.5rem] items-center border-b border-line px-5">
            <x-brand-logo :href="route('dashboard')" />
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main navigation">
            <p class="px-3 pb-1 font-mono text-[9px] font-medium uppercase tracking-[0.16em] text-faint">Monitoring</p>

            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">dashboard</span>
                <span>Overview</span>
            </a>

            <a href="{{ route('identity') }}" class="nav-link {{ request()->routeIs('identity*') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">fingerprint</span>
                <span>Digital footprint</span>
            </a>

            <a href="{{ route('findings') }}" class="nav-link {{ request()->routeIs('findings*') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">search</span>
                <span>Exposure report</span>
            </a>

            <a href="{{ route('credentials') }}" class="nav-link {{ request()->routeIs('credentials*') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">key</span>
                <span>Credential monitor</span>
            </a>

            <a href="{{ route('removals') }}" class="nav-link {{ request()->routeIs('removals*') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">delete</span>
                <span>Removal guidance</span>
            </a>

            <a href="{{ route('monitoring') }}" class="nav-link {{ request()->routeIs('monitoring*') || request()->routeIs('alerts*') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">notifications_active</span>
                <span>Monitoring</span>
            </a>

            <p class="px-3 pb-1 pt-4 font-mono text-[9px] font-medium uppercase tracking-[0.16em] text-faint">Tools</p>

            <a href="{{ route('password.check') }}" class="nav-link {{ request()->routeIs('password.check') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">password</span>
                <span>Password check</span>
            </a>

            <a href="{{ route('ip.check') }}" class="nav-link {{ request()->routeIs('ip.check') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">public</span>
                <span>IP check</span>
            </a>

            <p class="px-3 pb-1 pt-4 font-mono text-[9px] font-medium uppercase tracking-[0.16em] text-faint">Account</p>

            <a href="{{ route('reports') }}" class="nav-link {{ request()->routeIs('reports*') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">description</span>
                <span>Reports</span>
            </a>

            <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings*') ? 'nav-link-active font-semibold' : '' }}">
                <span class="material-symbols-outlined text-[20px]">settings</span>
                <span>Settings</span>
            </a>

            @if(auth()->user()?->isAdmin())
                <p class="px-3 pb-1 pt-4 font-mono text-[9px] font-medium uppercase tracking-[0.16em] text-warning">Administration</p>

                <a href="{{ route('admin.overview') }}" class="nav-link {{ request()->routeIs('admin.overview') ? 'nav-link-active font-semibold' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">monitoring</span>
                    <span>Admin overview</span>
                </a>

                <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users') ? 'nav-link-active font-semibold' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">group</span>
                    <span>Users</span>
                </a>

                <a href="{{ route('admin.activity') }}" class="nav-link {{ request()->routeIs('admin.activity') ? 'nav-link-active font-semibold' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">history</span>
                    <span>Activity log</span>
                </a>

                <a href="{{ route('admin.failed-jobs') }}" class="nav-link {{ request()->routeIs('admin.failed-jobs') ? 'nav-link-active font-semibold' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">error</span>
                    <span>Failed jobs</span>
                </a>

                <a href="{{ route('admin.email') }}" class="nav-link {{ request()->routeIs('admin.email') ? 'nav-link-active font-semibold' : '' }}">
                    <span class="material-symbols-outlined text-[20px]">outgoing_mail</span>
                    <span>Email delivery</span>
                </a>
            @endif
        </nav>

        @php $sidebarProfile = auth()->user()->latestProfile(); @endphp
        <div class="border-t border-line p-4">
            @if($sidebarProfile)
                <div class="mb-1.5 flex items-center justify-between text-xs text-muted">
                    <span>Privacy score</span>
                    <span class="font-semibold text-ink">{{ $sidebarProfile->privacy_score }}/100</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface">
                    <div class="h-full rounded-full bg-accent" style="width: {{ $sidebarProfile->privacy_score }}%"></div>
                </div>
                <p class="mt-2 truncate text-xs text-faint">{{ $sidebarProfile->full_name }}</p>
            @else
                <p class="text-xs text-muted">No identity yet.</p>
                <a href="{{ route('identity.create') }}" class="mt-1 inline-block text-xs font-medium text-accent hover:underline">Create an identity →</a>
            @endif
        </div>
    </aside>

    <!-- Main column -->
    <div class="flex min-h-screen flex-col lg:pl-64">
        <header class="ops-topbar sticky top-0 z-30 border-b">
            <div class="flex h-[4.5rem] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-1 items-center gap-3">
                    <button class="rounded-lg border border-transparent p-2 text-body transition hover:border-line hover:bg-elevated/50 hover:text-ink lg:hidden" @click="mobileNav = true" aria-label="Open navigation">
                        <span class="material-symbols-outlined text-[20px]">menu</span>
                    </button>

                    <form action="{{ route('findings') }}" method="GET" class="hidden w-full max-w-md items-center gap-2 rounded-xl border border-line bg-elevated/40 px-3 py-2 text-sm text-body shadow-inner transition focus-within:border-cyan focus-within:bg-elevated/65 sm:flex">
                        <span class="material-symbols-outlined text-[18px] text-faint">search</span>
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search findings, handles, sources…"
                               class="w-full border-0 bg-transparent p-0 text-sm text-ink placeholder:text-faint focus:ring-0" aria-label="Search findings">
                    </form>
                </div>

                <div class="flex items-center gap-2">
                    <livewire:notifications.notifications-panel />

                    <button type="button"
                            class="rounded-lg border border-transparent p-2 text-body transition hover:border-line hover:bg-elevated/50 hover:text-ink"
                            aria-label="Toggle dark mode"
                            x-data="{ dark: document.documentElement.classList.contains('dark') }"
                            @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('traceless-theme', dark ? 'dark' : 'light')">
                        <span class="material-symbols-outlined text-[20px]" x-text="dark ? 'light_mode' : 'dark_mode'">dark_mode</span>
                    </button>

                    <a href="{{ route('scans.create') }}" class="btn-primary">
                        <span class="material-symbols-outlined text-[18px]">search</span>
                        <span class="hidden sm:inline">New scan</span>
                    </a>

                    <div class="ml-1 flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border border-line bg-elevated/70 text-xs font-semibold text-body">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-transparent p-2 text-body transition hover:border-line hover:bg-elevated/50 hover:text-ink" title="Sign out" aria-label="Sign out">
                                <span class="material-symbols-outlined text-[20px]">logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-7 sm:px-6 lg:px-8">
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        <footer class="border-t border-line bg-canvas/50 px-6 py-4 font-mono text-[10px] text-faint backdrop-blur-sm">
            <div class="flex flex-col items-center justify-between gap-2 sm:flex-row">
                <span>TraceLess · public-source privacy monitoring</span>
                <span>Discovery uses WhatsMyName (CC BY-SA 4.0) and user-scanner (MIT)</span>
            </div>
        </footer>
    </div>

    <!-- Mobile navigation -->
    <div x-cloak x-show="mobileNav" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-ink/40" @click="mobileNav = false"></div>
        <div class="ops-sidebar relative flex h-full w-72 flex-col justify-between border-r border-line p-5">
            <div>
                <div class="mb-6 flex items-center justify-between">
                    <x-brand-logo :href="route('dashboard')" size="sm" />
                    <button @click="mobileNav = false" class="rounded-lg p-1.5 text-body hover:bg-surface" aria-label="Close navigation">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <nav class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="nav-link">Overview</a>
                    <a href="{{ route('identity') }}" class="nav-link">Digital footprint</a>
                    <a href="{{ route('findings') }}" class="nav-link">Exposure report</a>
                    <a href="{{ route('credentials') }}" class="nav-link">Credential monitor</a>
                    <a href="{{ route('removals') }}" class="nav-link">Removal guidance</a>
                    <a href="{{ route('monitoring') }}" class="nav-link">Monitoring</a>
                    <a href="{{ route('password.check') }}" class="nav-link">Password check</a>
                    <a href="{{ route('ip.check') }}" class="nav-link">IP check</a>
                    <a href="{{ route('reports') }}" class="nav-link">Reports</a>
                    <a href="{{ route('settings') }}" class="nav-link">Settings</a>
                    @if(auth()->user()?->isAdmin())
                        <a href="{{ route('admin.overview') }}" class="nav-link">Admin overview</a>
                        <a href="{{ route('admin.users') }}" class="nav-link">Admin users</a>
                        <a href="{{ route('admin.activity') }}" class="nav-link">Activity log</a>
                        <a href="{{ route('admin.failed-jobs') }}" class="nav-link">Failed jobs</a>
                        <a href="{{ route('admin.email') }}" class="nav-link">Email delivery</a>
                    @endif
                </nav>
            </div>
            <a href="{{ route('scans.create') }}" class="btn-primary w-full">New scan</a>
        </div>
    </div>

    <!-- Toast -->
    <div x-cloak x-show="toast" x-transition class="fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-xl border border-line bg-elevated/90 px-4 py-2.5 text-sm text-ink shadow-2xl backdrop-blur-xl">
        <span x-text="toastMsg"></span>
    </div>

    @livewireScripts
</body>
</html>
