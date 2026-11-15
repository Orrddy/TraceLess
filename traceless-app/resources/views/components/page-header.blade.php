@props(['eyebrow' => null, 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col justify-between gap-4 sm:flex-row sm:items-end']) }}>
    <div class="max-w-2xl">
        @if($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h1 class="display-title mt-1 text-[2rem] text-ink sm:text-[2.4rem]">{{ $title }}</h1>
        @if($description)<p class="mt-2 max-w-xl font-body text-sm leading-relaxed text-muted">{{ $description }}</p>@endif
    </div>
    @if(!$slot->isEmpty())
        <div class="flex shrink-0 items-center gap-2">{{ $slot }}</div>
    @endif
</div>
