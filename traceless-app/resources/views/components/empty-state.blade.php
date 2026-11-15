@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'glass-card flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg border border-line bg-surface text-muted">
        <span class="material-symbols-outlined text-[24px]" aria-hidden="true">{{ $icon }}</span>
    </span>
    <p class="font-editorial text-xl text-ink">{{ $title }}</p>
    @if($description)
        <p class="mt-2 max-w-sm font-body text-sm leading-relaxed text-muted">{{ $description }}</p>
    @endif
    @if(!$slot->isEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
