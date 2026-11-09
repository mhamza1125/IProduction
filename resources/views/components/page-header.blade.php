@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="min-w-0">
        <h1 class="text-xl font-semibold text-fg">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-subtle">{{ $description }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
