@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'rounded-lg bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800']) }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 border-b border-zinc-200 px-4 py-3 sm:px-6 dark:border-zinc-800">
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
                @endif
            </div>
            {{ $actions ?? '' }}
        </header>
    @endif

    <div @class(['px-4 py-4 sm:px-6' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex items-center justify-end gap-2 border-t border-zinc-200 bg-zinc-50 px-4 py-3 sm:px-6 dark:border-zinc-800 dark:bg-zinc-900/50 rounded-b-lg">
            {{ $footer }}
        </footer>
    @endisset
</section>
