<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#080A0D">
    <title>{{ $title ?? 'TraceLess // Personal Privacy Intelligence' }}</title>
    <meta name="description" content="TraceLess finds your exposed public footprint, identifies credential exposure, and provides actionable guidance to remediate your digital presence.">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@500;600;700&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="terminal-bg min-h-screen text-[#F1F4F7] antialiased selection:bg-[#55D6E8]/20 selection:text-white"
      x-data="{ mobileNav: false, toast: false, toastMsg: '' }"
      @notify.window="toastMsg = $event.detail.message || 'Updated'; toast = true; setTimeout(() => toast = false, 4000)"
      @toast.window="toastMsg = $event.detail.message || 'Updated'; toast = true; setTimeout(() => toast = false, 4000)">

    <!-- Top Tactical Ops Header -->
    <header class="sticky top-0 z-50 border-b border-white/[0.08] bg-[#080A0D]/90 backdrop-blur-md">
        <div class="mx-auto flex h-14 max-w-[1440px] items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand & Classification Badge -->
            <div class="flex items-center gap-6">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 group">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#101419] border border-white/10 text-[#55D6E8] group-hover:border-[#55D6E8]/50 transition-colors">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L3 7v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-9-5z" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 8v4M12 16h.01" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <div class="flex min-w-0 flex-col leading-none">
                        <span class="font-mono text-sm font-bold tracking-wider text-white">TRACE<span class="text-[#55D6E8]">LESS</span></span>
                        <span class="hidden font-mono text-[8px] uppercase tracking-[0.2em] text-[#A6B0BC]/60 xs:block sm:block">CONFIDENTIAL // OPS</span>
                    </div>
                </a>

                <!-- Navigation Links -->
                <nav class="hidden lg:flex items-center gap-6 font-mono text-[11px] uppercase tracking-wider text-[#A6B0BC]">
                    <a href="{{ route('home') }}#capabilities" class="hover:text-white transition-colors">Capabilities</a>
                    <a href="{{ route('home') }}#intelligence" class="hover:text-white transition-colors">Intelligence Matrix</a>
                    <a href="{{ route('home') }}#methodology" class="hover:text-white transition-colors">Methodology</a>
                    <a href="{{ route('home') }}#preparedness" class="hover:text-white transition-colors">2027 Readiness</a>
                </nav>
            </div>

            <!-- Auth Controls -->
            <div class="flex items-center gap-3">
                <div class="hidden sm:flex items-center gap-2 px-2.5 py-1 rounded bg-white/[0.03] border border-white/[0.06] font-mono text-[9px] text-[#A6B0BC]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#4ADE9B] animate-pulse"></span>
                    <span>SYSTEM ENCLAVE: SECURE</span>
                </div>

                @auth
                    <a href="{{ route('dashboard') }}" class="btn-ops-primary py-1.5 px-3.5 text-[11px]">
                        <span class="material-symbols-outlined text-[15px]">terminal</span>
                        <span>Terminal</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="btn-ops-secondary py-1.5 px-3 text-[11px]">Sign Out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-ops-secondary py-1.5 px-3 text-[11px]">Sign In</a>
                    <a href="{{ route('register') }}" class="btn-ops-primary py-1.5 px-3.5 text-[11px]">
                        <span class="hidden sm:inline">Scan Footprint</span>
                        <span class="sm:hidden">Scan</span>
                        <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main>
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Tactical Footer -->
    <footer class="border-t border-white/[0.08] bg-[#080A0D] py-12 px-4 sm:px-6 lg:px-8 font-mono text-[11px] text-[#A6B0BC]">
        <div class="mx-auto max-w-[1440px] grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-white font-bold">
                    <span class="text-[#55D6E8]">TRACELESS</span> // INTELLIGENCE
                </div>
                <p class="text-[12px] font-sans text-[#A6B0BC]/70 leading-relaxed">
                    Personal digital footprint intelligence terminal. Non-intrusive discovery across public profiles, open directories and disclosure archives.
                </p>
                <div class="text-[9px] text-[#5D6B7A]">
                    STATUS: ACTIVE OPS // ZERO PASSWORDS RETAINED
                </div>
            </div>

            <div>
                <div class="text-white uppercase tracking-widest text-[10px] mb-3">Intelligence Subsystems</div>
                <ul class="space-y-2 text-[10px]">
                    <li><a href="{{ route('home') }}#capabilities" class="hover:text-[#55D6E8] transition-colors">Federated Handle Correlation</a></li>
                    <li><a href="{{ route('home') }}#capabilities" class="hover:text-[#55D6E8] transition-colors">Data Broker Exposure Index</a></li>
                    <li><a href="{{ route('home') }}#capabilities" class="hover:text-[#55D6E8] transition-colors">Public Breach Metadata Audits</a></li>
                    <li><a href="{{ route('home') }}#capabilities" class="hover:text-[#55D6E8] transition-colors">Continuous Threat Monitoring</a></li>
                </ul>
            </div>

            <div>
                <div class="text-white uppercase tracking-widest text-[10px] mb-3">Security & Compliance</div>
                <ul class="space-y-2 text-[10px]">
                    <li><a href="{{ route('security') }}" class="hover:text-[#55D6E8] transition-colors">Zero-Knowledge Architecture</a></li>
                    <li><a href="{{ route('privacy') }}" class="hover:text-[#55D6E8] transition-colors">Privacy Charter & GDPR Rights</a></li>
                    <li><a href="{{ route('terms') }}" class="hover:text-[#55D6E8] transition-colors">Authorized Scanning Protocol</a></li>
                    <li><span class="text-[#4ADE9B]">● Local Python scanner boundary</span></li>
                </ul>
            </div>

            <div>
                <div class="text-white uppercase tracking-widest text-[10px] mb-3">Attribution & Integrity</div>
                <p class="text-[10px] text-[#A6B0BC]/70 leading-relaxed mb-3">
                    Reconnaissance powered by <a href="https://github.com/WebBreacher/WhatsMyName" target="_blank" rel="noopener" class="text-[#55D6E8] hover:underline">WhatsMyName</a> (CC BY-SA 4.0) and <a href="https://github.com/kaifcodec/user-scanner" target="_blank" rel="noopener" class="text-[#55D6E8] hover:underline">user-scanner</a> (MIT).
                </p>
                <div class="p-2.5 rounded bg-[#101419] border border-white/5 text-[9px] text-[#5D6B7A]">
                    TraceLess does not guarantee 100% third-party deletion. Scores are prioritisation models.
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-[1440px] pt-6 border-t border-white/[0.06] flex flex-col sm:flex-row items-center justify-between gap-4 text-[9px] text-[#5D6B7A]">
            <div>&copy; {{ date('Y') }} TRACELESS SECURITY LABS. ALL RIGHTS RESERVED.</div>
            <div class="flex items-center gap-4">
                <span>CLASSIFICATION: CONFIDENTIAL</span>
                <span>•</span>
                <span>GEO: GLOBAL ENCLAVE</span>
            </div>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
