{{--
    Search, filters, date range, export and rows-per-page for components using WithDataTable.
    <x-table.toolbar placeholder="Search..." dates :export="auth()->user()->can('customers.export')"> extra filters </x-table.toolbar>
--}}
@props(['placeholder' => 'Search...', 'dates' => false, 'export' => false])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800']) }}>
    <div class="relative w-full sm:w-72">
        <x-icon name="search" class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-zinc-400" />
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ $placeholder }}"
            data-search-shortcut
            aria-label="Search"
            class="block w-full rounded-md border-0 bg-white py-2 pl-8 pr-3 text-sm text-zinc-900 ring-1 ring-inset ring-zinc-300 placeholder:text-zinc-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-700"
        />
    </div>

    @if ($dates)
        <x-date-input wire:model="dateFrom" :live="true" aria-label="From date" class="w-32" />
        <span class="pb-2 text-xs text-zinc-500">to</span>
        <x-date-input wire:model="dateTo" :live="true" aria-label="To date" class="w-32" />
    @endif

    {{ $slot }}

    <div class="ml-auto flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <span wire:loading.delay class="animate-pulse">Loading…</span>

        @if ($export)
            <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false">
                <x-button variant="secondary" size="sm" x-on:click="open = ! open">Export</x-button>
                <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-32 rounded-md bg-white py-1 shadow-lg ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700">
                    <button type="button" wire:click="export('xlsx')" x-on:click="open = false" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">Excel (.xlsx)</button>
                    <button type="button" wire:click="export('csv')" x-on:click="open = false" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">CSV</button>
                </div>
            </div>
        @endif

        <label for="per-page">Rows</label>
        <select id="per-page" wire:model.live="perPage" class="rounded-md border-0 bg-white py-1 pl-2 pr-7 text-xs ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
            @foreach ([10, 25, 50, 100] as $n)
                <option value="{{ $n }}">{{ $n }}</option>
            @endforeach
        </select>
    </div>
</div>
