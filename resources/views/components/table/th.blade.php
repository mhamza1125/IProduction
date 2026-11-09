@props(['sortable' => null, 'sortField' => null, 'sortDirection' => 'asc', 'align' => 'left'])

@php
    $active = $sortable && $sortField === $sortable;
    $alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left');
@endphp

<th scope="col" {{ $attributes->merge(['class' => "px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-subtle whitespace-nowrap $alignClass"]) }}
    @if ($active) aria-sort="{{ $sortDirection === 'asc' ? 'ascending' : 'descending' }}" @endif>
    @if ($sortable)
        <button type="button" wire:click="sortBy('{{ $sortable }}')" class="group inline-flex items-center gap-1 uppercase hover:text-fg">
            {{ $slot }}
            <x-icon :name="$active ? ($sortDirection === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevron-up-down'"
                    @class(['size-3.5', 'opacity-30 group-hover:opacity-70' => ! $active]) />
        </button>
    @else
        {{ $slot }}
    @endif
</th>
