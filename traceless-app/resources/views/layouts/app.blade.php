<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#080A0D">
    <title>{{ $title ?? 'TraceLess // Privacy Command Center' }}</title>
    <meta name="description" content="TraceLess secure user dashboard and privacy posture command center.">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@500;600;700&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="terminal-bg font-sans text-[#F1F4F7] min-h-screen antialiased selection:bg-[#55D6E8]/20 selection:text-white"
      x-data="{ mobileNav: false, toast: false, toastMsg: '' }"
      @notify.window="toastMsg = $event.detail.message || 'Saved'; toast = true; setTimeout(() => toast = false, 4000)"
      @toast.window="toastMsg = $event.detail.message || 'Saved'; toast = true; setTimeout(() => toast = false, 4000)">

    <!-- Desktop Sidebar (260px tactical rail) -->
    <aside class="hidden lg:flex fixed inset-y-0 left-0 z-40 w-[260px] flex-col border-r border-white/[0.08] bg-[#0A0D12]">
        <!-- Brand & Tactical Terminal Header -->
        <div class="px-6 py-5 border-b border-white/[0.08]">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#101419] border border-white/10 text-[#55D6E8]">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 8v4M12 16h.01" stroke-linecap="round"/>
                    </svg>
                </span>
                <div class="flex flex-col leading-none">
                    <span class="font-mono text-base font-bold tracking-wider text-white">TRACE<span class="text-[#55D6E8]">LESS</span></span>
                    <span class="font-mono text-[8px] uppercase tracking-[0.2em] text-[#A6B0BC]/60 mt-1">OPS COMMAND</span>
                </div>
            </a>
        </div>

        <!-- Tactical ASCII Status Block -->
        <div class="px-4 py-3 mx-4 my-3 rounded bg-[#080A0D] border border-white/5 font-mono text-[9px] text-[#5D6B7A] leading-tight select-none">
            STATUS: ENCRYPTED // TR-782<br>
            EXPOSURE: ACTIVE SENTRY<br>
            ENCLAVE: {{ config('traceless.demo_mode') ? 'DEMO ADAPTER' : 'LOCAL PYTHON' }}
        </div>

        <!-- Primary Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 space-y-1 font-mono text-[11px]" aria-label="Command Navigation">
            <div class="px-3 pt-3 pb-1 text-[9px] uppercase tracking-widest text-[#5D6B7A]">Command Operations</div>
            
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'bg-[#171D24] text-[#55D6E8] border border-[#55D6E8]/30 font-semibold' : 'text-[#A6B0BC] hover:bg-white/5 hover:text-white' }}">
                <span class="material-symbols-outlined text-[17px]">space_dashboard</span>
                <span>OVERVIEW</span>
            </a>

            <a href="{{ route('identity') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('identity*') ? 'bg-[#171D24] text-[#55D6E8] border border-[#55D6E8]/30 font-semibold' : 'text-[#A6B0BC] hover:bg-white/5 hover:text-white' }}">
                <span class="material-symbols-outlined text-[17px]">fingerprint</span>
                <span>DIGITAL FOOTPRINT</span>
            </a>

            <a href="{{ route('findings') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('findings*') ? 'bg-[#171D24] text-[#55D6E8] border border-[#55D6E8]/30 font-semibold' : 'text-[#A6B0BC] hover:bg-white/5 hover:text-white' }}">
                <span class="material-symbols-outlined text-[17px]">radar</span>
                <span>EXPOSURE REPORT</span>
            </a>

            <a href="{{ route('credentials') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('credentials*') ? 'bg-[#171D24] text-[#55D6E8] border border-[#55D6E8]/30 font-semibold' : 'text-[#A6B0BC] hover:bg-white/5 hover:text-white' }}">
                <span class="material-symbols-outlined text-[17px]">vpn_key</span>
                <span>CREDENTIAL MONITOR</span>
            </a>

            <a href="{{ route('removals') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('removals*') ? 'bg-[#171D24] text-[#55D6E8] border border-[#55D6E8]/30 font-semibold' : 'text-[#A6B0BC] hover:bg-white/5 hover:text-white' }}">
                <span class="material-symbols-outlined text-[17px]">delete_sweep</span>
                <span>REMOVAL GUIDANCE</span>
            </a>

            <div class="px-3 pt-4 pb-1 text-[9px] uppercase tracking-widest text-[#5D6B7A]">Intelligence</div>

            <a href="{{ route('monitoring') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('monitoring*') || request()->routeIs('alerts*') ? 'bg-[#171D24] text-[#55D6E8] border border-[#55D6E8]/30 font-semibold' : 'text-[#A6B0BC] hover:bg-white/5 hover:text-white' }}">
                <span class="material-symbols-outlined text-[17px]">sensors</span>
                <span>CONTINUOUS MONITORING</span>
            </a>

            <a href="{{ route('reports') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('reports*') ? 'bg-[#171D24] text-[#55D6E8] border border-[#55D6E8]/30 font-semibold' : 'text-[#A6B0BC] hover:bg-white/5 hover:text-white' }}">
                <span class="material-symbols-outlined text-[17px]">description</span>
                <span>ACTIVITY LOG</span>
            </a>

            <a href="{{ route('settings') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg transition-colors {{ request()->routeIs('settings*') ? 'bg-[#171D24] text-[#55D6E8] border border-[#55D6E8]/30 font-semibold' : 'text-[#A6B0BC] hover:bg-white/5 hover:text-white' }}">
                <span class="material-symbols-outlined text-[17px]">settings</span>
                <span>SETTINGS</span>
            </a>
        </nav>

        <!-- Identity Posture Mini-card -->
        @php $sidebarProfile = auth()->user()->latestProfile(); @endphp
        <div class="p-4 border-t border-white/[0.08] bg-[#080A0D]/60">
            @if($sidebarProfile)
                <div class="flex items-center justify-between mb-2 font-mono text-[9px] text-[#A6B0BC]">
                    <span>POSTURE SCORE</span>
                    <span class="text-[#55D6E8] font-bold">{{ $sidebarProfile->privacy_score }}/100</span>
                </div>
                <div class="w-full h-1.5 bg-[#171D24] rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#55D6E8] to-[#4ADE9B]" style="width: {{ $sidebarProfile->privacy_score }}%"></div>
                </div>
                <div class="mt-3 flex items-center justify-between font-mono text-[9px] text-[#5D6B7A]">
                    <span class="truncate max-w-[130px]">{{ $sidebarProfile->full_name }}</span>
                    <span class="text-[#4ADE9B]">ACTIVE</span>
                </div>
            @else
                <div class="text-[10px] text-[#A6B0BC] font-mono mb-2">No identity mapped.</div>
                <a href="{{ route('identity.create') }}" class="text-[10px] font-mono text-[#55D6E8] hover:underline flex items-center gap-1">
                    Setup Identity →
                </a>
            @endif
        </div>
    </aside>

    <!-- Main Workspace -->
    <div class="lg:pl-[260px] min-h-screen flex flex-col">
        <!-- Compact Top Command Bar -->
        <header class="sticky top-0 z-30 border-b border-white/[0.08] bg-[#080A0D]/90 backdrop-blur-md">
            <div class="flex h-14 items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Left: Mobile Trigger & Search Bar -->
                <div class="flex items-center gap-3 flex-1 max-w-md">
                    <button class="lg:hidden p-1.5 rounded text-[#A6B0BC] hover:text-white" @click="mobileNav = true">
                        <span class="material-symbols-outlined text-[20px]">menu</span>
                    </button>

                    <form action="{{ route('findings') }}" method="GET" class="hidden sm:flex items-center gap-2 w-full px-3 py-1.5 rounded-lg bg-[#101419] border border-white/10 text-xs text-[#A6B0BC] focus-within:border-[#55D6E8]/40">
                        <span class="material-symbols-outlined text-[16px] text-[#5D6B7A]">search</span>
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search exposure records, handles, sources..." class="w-full bg-transparent border-0 p-0 text-xs text-white placeholder-[#5D6B7A] focus:ring-0 outline-none font-mono">
                    </form>
                </div>

                <!-- Right: Status Signals & Action -->
                <div class="flex items-center gap-4">
                    <div class="hidden xl:flex items-center gap-2 font-mono text-[9px] text-[#5D6B7A]">
                        @if(config('traceless.demo_mode'))
                            <span class="ops-badge ops-badge-amber">DEMO MODE</span>
                            <span>DETERMINISTIC LOCAL CATALOG</span>
                        @else
                            <span class="ops-badge ops-badge-emerald">LIVE</span>
                            <span>PYTHON SCANNER CONNECTED</span>
                        @endif
                    </div>

                    <a href="{{ route('scans.create') }}" class="btn-ops-primary py-1.5 px-3 text-[10px]">
                        <span class="material-symbols-outlined text-[14px]">radar</span>
                        <span>Deploy Scan</span>
                    </a>

                    <!-- User menu -->
                    <div class="flex items-center gap-2 font-mono text-xs">
                        <span class="h-7 w-7 rounded bg-[#171D24] border border-white/10 flex items-center justify-center font-bold text-[#55D6E8] text-[11px]">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-[10px] text-[#A6B0BC] hover:text-white transition-colors" title="Sign out">
                                <span class="material-symbols-outlined text-[18px]">logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Workspace Body -->
        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        <!-- Tactical Bottom Status Strip -->
        <footer class="border-t border-white/[0.06] bg-[#080A0D] px-6 py-4 font-mono text-[10px] text-[#5D6B7A] flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>CONFIDENTIAL // TRACELESS INTELLIGENCE ENGINE V4.9</div>
            <div>ATTRIBUTION: WHATSMYNAME (CC BY-SA 4.0) & USER-SCANNER (MIT)</div>
        </footer>
    </div>

    <!-- Mobile Drawer -->
    <div x-cloak x-show="mobileNav" class="fixed inset-0 z-50 lg:hidden" role="dialog">
        <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="mobileNav = false"></div>
        <div class="relative h-full w-[260px] bg-[#0A0D12] border-r border-white/10 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-6">
                    <span class="font-mono text-sm font-bold text-white">TRACE<span class="text-[#55D6E8]">LESS</span></span>
                    <button @click="mobileNav = false" class="text-[#A6B0BC] hover:text-white"><span class="material-symbols-outlined">close</span></button>
                </div>
                <nav class="space-y-1 font-mono text-xs">
                    <a href="{{ route('dashboard') }}" class="block px-3 py-2 text-[#A6B0BC] hover:text-white">OVERVIEW</a>
                    <a href="{{ route('identity') }}" class="block px-3 py-2 text-[#A6B0BC] hover:text-white">DIGITAL FOOTPRINT</a>
                    <a href="{{ route('findings') }}" class="block px-3 py-2 text-[#A6B0BC] hover:text-white">EXPOSURE REPORT</a>
                    <a href="{{ route('credentials') }}" class="block px-3 py-2 text-[#A6B0BC] hover:text-white">CREDENTIAL MONITOR</a>
                    <a href="{{ route('removals') }}" class="block px-3 py-2 text-[#A6B0BC] hover:text-white">REMOVAL GUIDANCE</a>
                    <a href="{{ route('monitoring') }}" class="block px-3 py-2 text-[#A6B0BC] hover:text-white">CONTINUOUS MONITORING</a>
                    <a href="{{ route('settings') }}" class="block px-3 py-2 text-[#A6B0BC] hover:text-white">SETTINGS</a>
                </nav>
            </div>
            <a href="{{ route('scans.create') }}" class="btn-ops-primary w-full text-center py-2 text-xs">Deploy Scan</a>
        </div>
    </div>

    @livewireScripts
</body>
</html>
