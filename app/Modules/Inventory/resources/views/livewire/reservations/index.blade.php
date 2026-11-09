<div>
    <x-page-header title="Reservations" description="Stock held for sales order lines. Reserved stock is not free for other orders. Reservations are made from Planning." />
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search SKU or item…">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="active">Active</option>
                <option value="consumed">Consumed</option>
                <option value="released">Released</option>
                <option value="">All</option>
            </select>
        </x-table.toolbar>
        <x-table empty="No reservations.">
            <x-slot:head>
                <x-table.th sortable="created_at" :sort-field="$sortField" :sort-direction="$sortDirection">Reserved</x-table.th>
                <x-table.th>For</x-table.th>
                <x-table.th>Item</x-table.th>
                <x-table.th>Lot</x-table.th>
                <x-table.th align="right">Quantity</x-table.th>
                <x-table.th align="right">Used</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>
            @foreach ($reservations as $r)
                <tr wire:key="r-{{ $r->id }}">
                    <x-table.td class="whitespace-nowrap">@date($r->created_at) <span class="text-xs text-subtle">{{ $r->creator?->name }}</span></x-table.td>
                    <x-table.td class="font-mono text-xs">{{ method_exists($r->demand, 'reservationLabel') ? $r->demand->reservationLabel() : class_basename($r->demand_type).' #'.$r->demand_id }}</x-table.td>
                    <x-table.td><span class="font-mono text-xs">{{ $r->variant->sku }}</span> {{ $r->variant->item->name }}</x-table.td>
                    <x-table.td class="font-mono text-xs">{{ $r->lot?->lot_number ?? 'Any' }}</x-table.td>
                    <x-table.td align="right">@qty($r->quantity)</x-table.td>
                    <x-table.td align="right">@qty($r->consumed_quantity)</x-table.td>
                    <x-table.td><x-status :status="$r->status" /> @if ($r->release_reason)<p class="text-xs text-subtle">{{ $r->release_reason }}</p>@endif</x-table.td>
                    <x-table.td align="right">
                        @if ($r->status === 'active')
                            @can('reservations.cancel')
                                <x-button variant="ghost" size="sm" wire:click="confirmRelease({{ $r->id }})">Release</x-button>
                            @endcan
                        @endif
                    </x-table.td>
                </tr>
                @if ($releasing === $r->id)
                    <tr><td colspan="8" class="bg-sunken px-4 py-3">
                        <form wire:submit="release" class="flex flex-wrap items-end gap-2">
                            <x-input label="Reason for releasing" wire:model="reason" class="min-w-72 flex-1" required />
                            <x-button type="submit" variant="danger">Release</x-button>
                            <x-button variant="ghost" wire:click="$set('releasing', null)">Close</x-button>
                        </form>
                    </td></tr>
                @endif
            @endforeach
        </x-table>
        @if ($reservations->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $reservations->links() }}</div>
        @endif
    </x-card>
</div>
