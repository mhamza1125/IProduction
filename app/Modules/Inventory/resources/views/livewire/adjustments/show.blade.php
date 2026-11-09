<div>
    <x-page-header :title="'Stock adjustment '.$adjustment->number" :description="\App\Support\Format::date($adjustment->adjustment_date).' · '.($adjustment->reason?->name ?? '')">
        @if ($adjustment->isDraft())
            @can('stock_adjustments.delete')
                <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft {{ $adjustment->number }}?">Delete</x-button>
            @endcan
        @endif
    </x-page-header>

    <div class="mb-6"><x-document-actions :document="$adjustment" :edit-route="route('inventory.adjustments.edit', $adjustment)" /></div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Lines" :padding="false">
                <x-table>
                    <x-slot:head>
                        <x-table.th>Item</x-table.th><x-table.th>Location · stage · status</x-table.th><x-table.th>Lot</x-table.th>
                        @if ($adjustment->from_count) <x-table.th align="right">System</x-table.th><x-table.th align="right">Counted</x-table.th> @endif
                        <x-table.th align="right">Change</x-table.th>
                        @can('cost.view') <x-table.th align="right">Unit cost</x-table.th> @endcan
                    </x-slot:head>
                    @foreach ($lines as $l)
                        <tr wire:key="l-{{ $l->id }}">
                            <x-table.td>{{ $l->variant->fullLabel() }}</x-table.td>
                            <x-table.td class="text-xs text-subtle">{{ collect([$l->location->name, $l->stage?->name, $statuses[$l->status]])->filter()->implode(' · ') }}</x-table.td>
                            <x-table.td class="font-mono text-xs">{{ $l->lot?->lot_number ?? ((float) $l->quantity > 0 ? 'new lot' : 'oldest first') }}</x-table.td>
                            @if ($adjustment->from_count) <x-table.td align="right">@qty($l->system_quantity)</x-table.td><x-table.td align="right">@qty($l->counted_quantity)</x-table.td> @endif
                            <x-table.td align="right" @class(['font-semibold', 'text-success' => (float) $l->quantity > 0, 'text-danger' => (float) $l->quantity < 0])>{{ (float) $l->quantity > 0 ? '+' : '' }}@qty($l->quantity) {{ $l->variant->item->stockUnit->symbol }}</x-table.td>
                            @can('cost.view') <x-table.td align="right">@money($l->unit_cost, null, 4)</x-table.td> @endcan
                        </tr>
                    @endforeach
                </x-table>
            </x-card>

            @if ($movements->isNotEmpty())
                <x-card title="Stock movements" :padding="false">
                    <x-table>
                        <x-slot:head><x-table.th>Lot</x-table.th><x-table.th>Location</x-table.th><x-table.th align="right">In</x-table.th><x-table.th align="right">Out</x-table.th></x-slot:head>
                        @foreach ($movements as $m)
                            <tr><x-table.td class="font-mono text-xs"><a href="{{ route('inventory.lots.show', $m->lot_id) }}" wire:navigate class="text-link hover:underline">{{ $m->lot->lot_number }}</a></x-table.td><x-table.td>{{ $m->location->name }}</x-table.td><x-table.td align="right">{{ (float) $m->qty_in ? \App\Support\Format::qty($m->qty_in) : '' }}</x-table.td><x-table.td align="right">{{ (float) $m->qty_out ? \App\Support\Format::qty($m->qty_out) : '' }}</x-table.td></tr>
                        @endforeach
                    </x-table>
                </x-card>
            @endif

            <x-card title="Attachments"><livewire:core::shared.attachments :model="$adjustment" :key="'att-'.$adjustment->id" /></x-card>
        </div>
        <div class="space-y-6">
            @if ($adjustment->notes)<x-card title="Notes on document"><p class="text-sm">{{ $adjustment->notes }}</p></x-card>@endif
            <x-card title="Notes"><livewire:core::shared.notes :model="$adjustment" :key="'n-'.$adjustment->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$adjustment" :key="'h-'.$adjustment->id" /></x-card>
        </div>
    </div>
</div>
