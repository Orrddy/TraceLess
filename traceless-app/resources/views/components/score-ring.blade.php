@props(['score' => 0, 'size' => 176, 'label' => 'Privacy score', 'stroke' => 9])

@php
    $score = max(0, min(100, (int) $score));
    $radius = 52;
    $circumference = 2 * M_PI * $radius;
    $offset = $circumference - ($circumference * $score / 100);
    $color = $score >= 85 ? '#4ADE9B' : ($score >= 65 ? '#55D6E8' : ($score >= 40 ? '#E9B45D' : '#EF6262'));
    $uid = 'ring-'.uniqid();
@endphp

<div {{ $attributes->merge(['class' => 'relative inline-flex items-center justify-center']) }} style="width: {{ $size }}px; height: {{ $size }}px;">
    <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90" aria-hidden="true">
        <defs>
            <filter id="{{ $uid }}" x="-40%" y="-40%" width="180%" height="180%">
                <feGaussianBlur stdDeviation="2.2" result="blur"/>
                <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
            </filter>
            <linearGradient id="{{ $uid }}-grad" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="{{ $color }}"/>
                <stop offset="100%" stop-color="#4ADE9B"/>
            </linearGradient>
        </defs>
        <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="#FFFFFF" stroke-opacity=".09" stroke-width="{{ $stroke }}"/>
        <circle cx="60" cy="60" r="{{ $radius }}"
                fill="none"
                stroke="url(#{{ $uid }}-grad)"
                stroke-width="{{ $stroke }}"
                stroke-linecap="round"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $offset }}"
                filter="url(#{{ $uid }})"
                style="transition: stroke-dashoffset 1.1s cubic-bezier(.22,.8,.24,1);"/>
        <circle cx="60" cy="60" r="{{ $radius - 9 }}" fill="none" stroke="#FFFFFF" stroke-opacity=".18" stroke-width=".6" stroke-dasharray="1 5"/>
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center">
        <span class="font-mono text-[2.15rem] font-semibold leading-none tracking-tight text-ink">{{ $score }}</span>
        <span class="mt-1 font-mono text-[9px] uppercase tracking-[0.16em] text-muted">{{ $label }}</span>
        <span class="mt-0.5 font-mono text-[8px] text-muted/70">/ 100</span>
    </div>
</div>
