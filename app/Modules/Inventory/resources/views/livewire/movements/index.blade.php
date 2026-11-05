<div>
    <x-page-header title="Stock movements" description="The stock ledger: every quantity in and out, from posted documents only. Movements are never edited or deleted." />

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search SKU, item or lot…" dates :export="auth()->user()->can('stock_movements.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All statuses</option>
                @foreach ($statuses as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="location" aria-label="Location" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All locations</option>
                @foreach ($locations as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No movements.">
            <x-slot:head>
                <x-table.th sortable="movement_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Document</x-table.th>
                <x-table.th>Item</x-table.th>
                <x-table.th>Lot</x-table.th>
                <x-table.th>Stage · status · location</x-table.th>
                <x-table.th align="right">In</x-table.th>
                <x-table.th align="right">Out</x-table.th>
                @can('cost.view') <x-table.th align="right">Cost</x-table.th> @endcan
            </x-slot:head>
            @foreach ($movements as $m)
                <tr wire:key="m-{{ $m->id }}">
                    <x-table.td class="whitespace-nowrap">@date($m->movement_date)</x-table.td>
                    <x-table.td class="whitespace-nowrap font-mono text-xs">{{ \App\Modules\Inventory\Livewire\Movements\Index::documentLabel($m) }}</x-table.td>
                    <x-table.td><span class="font-mono text-xs">{{ $m->variant->sku }}</span> {{ $m->variant->item->name }}</x-table.td>
                    <x-table.td class="font-mono text-xs"><a href="{{ route('inventory.lots.show', $m->lot_id) }}" wire:navigate class="text-brand-600 hover:underline">{{ $m->lot->lot_number }}</a></x-table.td>
                    <x-table.td class="text-xs text-zinc-500">{{ collect([$m->stage?->name, $statuses[$m->status] ?? $m->status, $m->location->name])->filter()->implode(' · ') }}</x-table.td>
                    <x-table.td align="right" class="text-green-700 dark:text-green-400">{{ (float) $m->qty_in ? \App\Support\Format::qty($m->qty_in) : '' }}</x-table.td>
                    <x-table.td align="right" class="text-red-700 dark:text-red-400">{{ (float) $m->qty_out ? \App\Support\Format::qty($m->qty_out) : '' }}</x-table.td>
                    @can('cost.view') <x-table.td align="right">@money($m->total_cost)</x-table.td> @endcan
                </tr>
            @endforeach
        </x-table>
        @if ($movements->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $movements->links() }}</div>
        @endif
    </x-card>
</div>
