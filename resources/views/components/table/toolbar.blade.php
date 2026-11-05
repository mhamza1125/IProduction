{{-- Search box + per-page selector for components using WithDataTable. Extra filters go in the slot. --}}
@props(['placeholder' => 'Search...'])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800']) }}>
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

    {{ $slot }}

    <div class="ml-auto flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
        <span wire:loading.delay class="animate-pulse">Loading…</span>
        <label for="per-page">Rows</label>
        <select id="per-page" wire:model.live="perPage" class="rounded-md border-0 bg-white py-1 pl-2 pr-7 text-xs ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
            @foreach ([10, 25, 50, 100] as $n)
                <option value="{{ $n }}">{{ $n }}</option>
            @endforeach
        </select>
    </div>
</div>
