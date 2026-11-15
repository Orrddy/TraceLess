@props(['label', 'value', 'icon' => 'analytics', 'tone' => 'ink', 'hint' => null, 'href' => null])

@php
    $toneMap = [
        'ink' => 'text-[#F1F4F7]',
        'blue' => 'text-[#55D6E8]',
        'cyan' => 'text-[#55D6E8]',
        'coral' => 'text-[#EF6262]',
        'amber' => 'text-[#E9B45D]',
        'emerald' => 'text-[#4ADE9B]',
        'violet' => 'text-[#9C8CFF]',
    ];
    $iconTone = $toneMap[$tone] ?? $toneMap['ink'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'stat-card hover-lift group block']) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="mono-label">{{ $label }}</p>
            <p class="mt-2 font-mono text-[1.75rem] font-semibold leading-none tracking-tight {{ $iconTone }}">{{ $value }}</p>
            @if($hint)<p class="mt-2 font-body text-[11px] leading-snug text-muted">{{ $hint }}</p>@endif
        </div>
        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/[0.03] border border-white/10 {{ $iconTone }}>
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon }}</span>
        </span>
    </div>
</{{ $tag }}>
