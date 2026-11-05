{{--
    Bind to a boolean Livewire property:
    <x-modal wire:model="showForm" title="Edit currency"> ... <x-slot:footer>...</x-slot:footer> </x-modal>
    Opening/closing happens in the browser (no extra request); Escape closes it.
--}}
@props(['title' => null, 'maxWidth' => 'lg'])

@php
    $widths = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl'];
@endphp

<div
    x-data="{ open: @entangle($attributes->wire('model')) }"
    x-show="open"
    x-on:keydown.escape.window="open = false"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    role="dialog"
    aria-modal="true"
>
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-zinc-900/50" x-on:click="open = false"></div>

    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
        <div
            x-show="open"
            x-transition
            x-trap.noscroll="open"
            class="relative w-full {{ $widths[$maxWidth] ?? $widths['lg'] }} rounded-lg bg-white shadow-xl ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800"
        >
            @if ($title)
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-3 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $title }}</h3>
                    <button type="button" x-on:click="open = false" class="rounded p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                        <x-icon name="x" class="size-4" />
                        <span class="sr-only">Close</span>
                    </button>
                </div>
            @endif

            <div class="px-5 py-4">{{ $slot }}</div>

            @isset($footer)
                <div class="flex justify-end gap-2 border-t border-zinc-200 bg-zinc-50 px-5 py-3 rounded-b-lg dark:border-zinc-800 dark:bg-zinc-900/50">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
