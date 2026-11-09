<div>
    <x-page-header title="Items" description="Raw materials, semi-finished and finished goods. Stock and documents use each item's variants.">
        @can('items.create')
            <x-button :href="route('catalog.items.create')" icon="plus">New item</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search code, name, description…" :export="auth()->user()->can('items.export')">
            <select wire:model.live="type" aria-label="Type" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All types</option>
                @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="category" aria-label="Category" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All categories</option>
                @foreach ($categories as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
            </select>
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="">All</option>
            </select>
        </x-table.toolbar>

        <x-table empty="No items found.">
            <x-slot:head>
                <x-table.th sortable="code" :sort-field="$sortField" :sort-direction="$sortDirection">Code</x-table.th>
                <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Name</x-table.th>
                <x-table.th sortable="type" :sort-field="$sortField" :sort-direction="$sortDirection">Type</x-table.th>
                <x-table.th>Category</x-table.th>
                <x-table.th>Unit</x-table.th>
                <x-table.th>Use</x-table.th>
                <x-table.th align="right">Variants</x-table.th>
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($items as $item)
                <tr wire:key="i-{{ $item->id }}" class="hover:bg-hover">
                    <x-table.td class="font-mono"><a href="{{ route('catalog.items.show', $item) }}" wire:navigate class="text-link hover:underline">{{ $item->code }}</a></x-table.td>
                    <x-table.td class="font-medium">{{ $item->name }}</x-table.td>
                    <x-table.td>{{ $types[$item->type] }}</x-table.td>
                    <x-table.td>{{ $item->category?->name }}</x-table.td>
                    <x-table.td>{{ $item->stockUnit->symbol }}</x-table.td>
                    <x-table.td class="whitespace-nowrap text-xs text-subtle">{{ collect(['Buy' => $item->can_buy, 'Make' => $item->can_make, 'Sell' => $item->can_sell])->filter()->keys()->implode(' · ') }}</x-table.td>
                    <x-table.td align="right">{{ $item->variants_count }}</x-table.td>
                    <x-table.td><x-status :status="$item->is_active ? 'active' : 'inactive'" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($items->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $items->links() }}</div>
        @endif
    </x-card>
</div>
