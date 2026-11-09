{{--
    Search, filters, date range, export and rows-per-page for components using WithDataTable.
    <x-table.toolbar placeholder="Search..." dates :export="auth()->user()->can('customers.export')"> extra filters </x-table.toolbar>
--}}
@props(['placeholder' => 'Search...', 'dates' => false, 'export' => false])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3 border-b border-line px-4 py-3']) }}>
    <div class="relative w-full sm:w-72">
        <x-icon name="search" class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-subtle" />
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ $placeholder }}"
            data-search-shortcut
            aria-label="Search"
            class="block w-full rounded-md border-0 bg-surface py-2 pl-8 pr-3 text-sm text-fg ring-1 ring-inset ring-line-strong placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-brand-600"
        />
    </div>

    @if ($dates)
        <x-date-input wire:model="dateFrom" :live="true" aria-label="From date" class="w-32" />
        <span class="pb-2 text-xs text-subtle">to</span>
        <x-date-input wire:model="dateTo" :live="true" aria-label="To date" class="w-32" />
    @endif

    {{ $slot }}

    <div class="ml-auto flex items-center gap-2 text-xs text-subtle">
        <span wire:loading.delay class="animate-pulse">Loading…</span>

        @if ($export)
            <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false">
                <x-button variant="secondary" size="sm" x-on:click="open = ! open">Export</x-button>
                <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-32 rounded-md bg-raised py-1 text-fg shadow-lg ring-1 ring-line-strong/40">
                    <button type="button" wire:click="export('xlsx')" x-on:click="open = false" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-hover">Excel (.xlsx)</button>
                    <button type="button" wire:click="export('csv')" x-on:click="open = false" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-hover">CSV</button>
                </div>
            </div>
        @endif

        <label for="per-page">Rows</label>
        <select id="per-page" wire:model.live="perPage" class="rounded-md border-0 bg-surface py-1 pl-2 pr-7 text-xs ring-1 ring-inset ring-line-strong">
            @foreach ([10, 25, 50, 100] as $n)
                <option value="{{ $n }}">{{ $n }}</option>
            @endforeach
        </select>
    </div>
</div>
