<div>
    <x-page-header title="Lots" description="Every inbound source creates a lot with its own source and cost." />
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search lot, SKU or item…" dates :export="auth()->user()->can('lots.export')">
            <select wire:model.live="origin" aria-label="Origin" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All origins</option>
                @foreach ($origins as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No lots.">
            <x-slot:head>
                <x-table.th sortable="lot_number" :sort-field="$sortField" :sort-direction="$sortDirection">Lot</x-table.th>
                <x-table.th>Item</x-table.th>
                <x-table.th>Origin</x-table.th>
                <x-table.th sortable="received_date" :sort-field="$sortField" :sort-direction="$sortDirection">Received</x-table.th>
                <x-table.th>From</x-table.th>
                <x-table.th align="right">On hand</x-table.th>
            </x-slot:head>
            @foreach ($lots as $lot)
                <tr wire:key="l-{{ $lot->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('inventory.lots.show', $lot) }}" wire:navigate class="text-link hover:underline">{{ $lot->lot_number }}</a></x-table.td>
                    <x-table.td><span class="font-mono text-xs">{{ $lot->variant->sku }}</span> {{ $lot->variant->item->name }}</x-table.td>
                    <x-table.td>{{ $origins[$lot->origin] ?? $lot->origin }}</x-table.td>
                    <x-table.td>@date($lot->received_date)</x-table.td>
                    <x-table.td>{{ $lot->vendor?->name ?? $lot->contractor?->name }}</x-table.td>
                    <x-table.td align="right">@qty($lot->quantity_on_hand ?? '0')</x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($lots->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $lots->links() }}</div>
        @endif
    </x-card>
</div>
