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
    <div x-show="open" x-transition.opacity class="fixed inset-0 bg-black/50" x-on:click="open = false"></div>

    <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
        <div
            x-show="open"
            x-transition
            x-trap.noscroll="open"
            class="relative w-full {{ $widths[$maxWidth] ?? $widths['lg'] }} rounded-lg bg-raised text-fg shadow-xl ring-1 ring-line-strong/40"
        >
            @if ($title)
                <div class="flex items-center justify-between border-b border-line px-5 py-3">
                    <h3 class="text-sm font-semibold text-fg">{{ $title }}</h3>
                    <button type="button" x-on:click="open = false" class="rounded p-1 text-subtle hover:text-fg">
                        <x-icon name="x" class="size-4" />
                        <span class="sr-only">Close</span>
                    </button>
                </div>
            @endif

            <div class="px-5 py-4">{{ $slot }}</div>

            @isset($footer)
                <div class="flex justify-end gap-2 border-t border-line bg-sunken px-5 py-3 rounded-b-lg">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
