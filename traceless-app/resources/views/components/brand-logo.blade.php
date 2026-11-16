@props(['size' => 'md', 'href' => null, 'tagline' => null])

@php
    $dimensions = [
        'sm' => 'h-7 w-7',
        'md' => 'h-9 w-9',
        'lg' => 'h-11 w-11',
    ][$size] ?? 'h-9 w-9';
@endphp

<a href="{{ $href ?? route('home') }}" {{ $attributes->merge(['class' => 'group inline-flex items-center gap-2.5']) }} aria-label="TraceLess home">
    <img src="{{ asset('brand/traceless-mark.svg') }}" alt="" width="44" height="44"
         class="{{ $dimensions }} shrink-0 transition-transform duration-300 group-hover:scale-105" />
    <span class="flex flex-col leading-tight">
        <span class="font-display text-lg font-semibold tracking-[-0.045em] text-ink">TraceLess</span>
        @if($tagline)
            <span class="mt-0.5 text-xs text-faint">{{ $tagline }}</span>
        @endif
    </span>
</a>
