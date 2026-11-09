@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'rounded-lg bg-surface shadow-sm ring-1 ring-line']) }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 border-b border-line px-4 py-3 sm:px-6">
            <div>
                <h2 class="text-sm font-semibold text-fg">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-0.5 text-xs text-subtle">{{ $description }}</p>
                @endif
            </div>
            {{ $actions ?? '' }}
        </header>
    @endif

    <div @class(['px-4 py-4 sm:px-6' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex items-center justify-end gap-2 border-t border-line bg-sunken px-4 py-3 sm:px-6 rounded-b-lg">
            {{ $footer }}
        </footer>
    @endisset
</section>
