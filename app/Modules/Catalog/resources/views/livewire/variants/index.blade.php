<div>
    <x-page-header title="Variants" description="Every stockable version of every item. Manage variants from the item page." />

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search SKU, barcode, item…" :export="auth()->user()->can('items.export')">
            <select wire:model.live="type" aria-label="Type" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All types</option>
                @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="">All</option>
            </select>
        </x-table.toolbar>
        <x-table empty="No variants found.">
            <x-slot:head>
                <x-table.th sortable="sku" :sort-field="$sortField" :sort-direction="$sortDirection">SKU</x-table.th>
                <x-table.th>Item</x-table.th>
                <x-table.th sortable="label" :sort-field="$sortField" :sort-direction="$sortDirection">Variant</x-table.th>
                <x-table.th>Barcode</x-table.th>
                <x-table.th align="right">Price</x-table.th>
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($variants as $v)
                <tr wire:key="v-{{ $v->id }}">
                    <x-table.td class="font-mono">{{ $v->sku }}</x-table.td>
                    <x-table.td><a href="{{ route('catalog.items.show', $v->item_id) }}" wire:navigate class="text-link hover:underline">{{ $v->item->code }}</a> {{ $v->item->name }}</x-table.td>
                    <x-table.td>{{ $v->label !== '' ? $v->label : 'Standard' }}</x-table.td>
                    <x-table.td class="font-mono text-xs">{{ $v->barcode }}</x-table.td>
                    <x-table.td align="right">{{ \App\Support\Format::money($v->sellingPrice(), null, 4) }}</x-table.td>
                    <x-table.td><x-status :status="$v->is_active ? 'active' : 'inactive'" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($variants->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $variants->links() }}</div>
        @endif
    </x-card>
</div>
