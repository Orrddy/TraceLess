@props(['risk' => 'low', 'compact' => false])

@php
    $risk = strtolower($risk);
    $map = [
        'critical' => ['badge-critical', 'dangerous', 'Critical'],
        'high' => ['badge-high', 'warning', 'High'],
        'moderate' => ['badge-moderate', 'error', 'Moderate'],
        'low' => ['badge-low', 'info', 'Low'],
    ];
    [$class, $icon, $label] = $map[$risk] ?? $map['low'];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$class]) }}>
    @unless($compact)<span class="material-symbols-outlined text-[12px]" aria-hidden="true">{{ $icon }}</span>@endunless
    {{ $label }}
</span>
