@php($d = $delivery)
<div x-data="{ ask: null }">
    <x-page-header :title="'Delivery '.$d->number" :description="$d->customer->name.' · '.$d->currency_code.' · '.\App\Support\Format::date($d->delivery_date)">
        <x-status :status="$d->status" />
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($d->isDraft())
            @can('deliveries.edit')
                <x-button variant="secondary" :href="route('delivery.deliveries.edit', $d)" icon="pencil">Edit</x-button>
                <x-button wire:click="allocate">Allocate stock</x-button>
            @endcan
            @can('deliveries.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan
        @endif
        @if ($d->status === 'packed')
            @can('deliveries.post') <x-button wire:click="dispatchDelivery" wire:confirm="Dispatch {{ $d->number }}? Stock leaves and cost of goods sold is posted.">Dispatch</x-button> @endcan
        @endif
        @if (in_array($d->status, ['allocated', 'packed'], true))
            @can('deliveries.edit') <x-button variant="ghost" x-on:click="ask = 'unallocate'">Back to draft</x-button> @endcan
        @endif
        @if (in_array($d->status, ['draft', 'allocated', 'packed'], true))
            @can('deliveries.cancel') <x-button variant="ghost" x-on:click="ask = 'cancel'">Cancel</x-button> @endcan
        @endif
        @if (! $d->isDraft() && $d->status !== 'cancelled')
            @can('deliveries.print') <a href="{{ route('delivery.deliveries.print', $d) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium text-muted hover:bg-hover"><x-icon name="printer" class="size-4" /> Delivery note</a> @endcan
            @can('packing_lists.print') <a href="{{ route('delivery.deliveries.packing', $d) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium text-muted hover:bg-hover"><x-icon name="printer" class="size-4" /> Packing list</a> @endcan
        @endif
        @if ($d->status === 'dispatched')
            @can('invoices.create') <x-button variant="secondary" :href="route('delivery.invoices.create', ['customer' => $d->customer_id])">Invoice</x-button> @endcan
        @endif
        <div x-show="ask" x-cloak class="mt-2 flex w-full flex-wrap items-end gap-2 rounded-md bg-sunken p-3">
            <x-input label="Reason" wire:model="reason" class="min-w-64 flex-1" />
            <x-button x-show="ask === 'unallocate'" wire:click="unallocate" x-on:click="ask = null">Back to draft</x-button>
            <x-button x-show="ask === 'cancel'" variant="danger" wire:click="cancel" x-on:click="ask = null">Cancel delivery</x-button>
            <x-button variant="ghost" x-on:click="ask = null">Close</x-button>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Lines" :padding="false">
                <x-table>
                    <x-slot:head><x-table.th>Order</x-table.th><x-table.th>Item</x-table.th><x-table.th align="right">Quantity</x-table.th><x-table.th>Lots</x-table.th><x-table.th align="right">Packed</x-table.th><x-table.th align="right">Returned</x-table.th></x-slot:head>
                    @foreach ($d->lines as $l)
                        <tr wire:key="dl-{{ $l->id }}">
                            <x-table.td class="font-mono text-xs"><a href="{{ route('sales.orders.show', $l->orderLine->sales_order_id) }}" wire:navigate class="text-link hover:underline">{{ $l->orderLine->order->number }}</a></x-table.td>
                            <x-table.td wide>{{ $l->variant->fullLabel() }}@if ($l->source_status === 'rejected')<x-badge color="red">rejected stock</x-badge>@endif @if ($l->over_reason)<div class="text-xs text-warning">More than ordered: {{ $l->over_reason }}</div>@endif</x-table.td>
                            <x-table.td align="right">@qty($l->quantity) {{ $l->variant->item->stockUnit->symbol }}</x-table.td>
                            <x-table.td class="text-xs">@foreach ($l->allocations as $a)<div><a href="{{ route('inventory.lots.show', $a->lot) }}" wire:navigate class="font-mono text-link hover:underline">{{ $a->lot->lot_number }}</a> · @qty($a->quantity)</div>@endforeach</x-table.td>
                            <x-table.td align="right">@qty($packed[$l->id] ?? '0')</x-table.td>
                            <x-table.td align="right">@qty($l->returned_qty)</x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>

            @if (! $d->isDraft() && $d->status !== 'cancelled')
                <x-card title="Packing list" :description="$totals ? $totals['cartons'].' cartons · '.\App\Support\Format::qty($totals['pieces']).' pieces · gross '.$totals['gross'].' kg · net '.$totals['net'].' kg' : null" :padding="false">
                    <x-table empty="No cartons yet.">
                        <x-slot:head><x-table.th>Carton</x-table.th><x-table.th>Type · size</x-table.th><x-table.th>Contents</x-table.th><x-table.th align="right">Gross / net kg</x-table.th><x-table.th>Marks</x-table.th><x-table.th></x-table.th></x-slot:head>
                        @foreach ($d->packingList?->cartons ?? [] as $c)
                            <tr wire:key="c-{{ $c->id }}">
                                <x-table.td class="font-semibold">{{ $c->carton_number }}</x-table.td>
                                <x-table.td class="text-xs">{{ $c->carton_type }}@if ($c->length) · {{ (float) $c->length }}×{{ (float) $c->width }}×{{ (float) $c->height }}@endif</x-table.td>
                                <x-table.td class="text-xs">@foreach ($c->lines as $cl)<div>{{ $cl->deliveryLine->variant->sku }} · @qty($cl->quantity)</div>@endforeach</x-table.td>
                                <x-table.td align="right">{{ $c->gross_weight !== null ? (float) $c->gross_weight : '' }} / {{ $c->net_weight !== null ? (float) $c->net_weight : '' }}</x-table.td>
                                <x-table.td class="text-xs">{{ $c->marks }}</x-table.td>
                                <x-table.td>@if ($d->status === 'allocated') @can('packing_lists.edit') <x-button size="sm" variant="ghost" icon="trash" wire:click="removeCarton({{ $c->id }})" aria-label="Remove carton" /> @endcan @endif</x-table.td>
                            </tr>
                        @endforeach
                    </x-table>
                    @if ($d->status === 'allocated')
                        @can('packing_lists.edit')
                            <form wire:submit="addCarton" class="space-y-3 border-t border-line p-4">
                                <h3 class="text-sm font-semibold">Add carton</h3>
                                <div class="grid gap-3 sm:grid-cols-4">
                                    <x-input label="Type" wire:model="carton.carton_type" />
                                    <x-input label="L × W × H (cm)" wire:model="carton.length" type="number" step="any" placeholder="L" />
                                    <x-input label="&nbsp;" wire:model="carton.width" type="number" step="any" placeholder="W" />
                                    <x-input label="&nbsp;" wire:model="carton.height" type="number" step="any" placeholder="H" />
                                    <x-input label="Gross kg" wire:model="carton.gross_weight" type="number" step="any" />
                                    <x-input label="Net kg" wire:model="carton.net_weight" type="number" step="any" />
                                    <x-input label="Marks" wire:model="carton.marks" />
                                    <x-input label="Identical cartons" wire:model="repeat" type="number" min="1" max="500" />
                                </div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($d->lines as $l)
                                        <label class="flex items-center justify-between gap-2 text-sm"><span>{{ $l->orderLine->order->number }} · {{ $l->variant->sku }} <span class="text-xs text-subtle">({{ \App\Support\Format::qty(\App\Support\Decimal::sub($l->quantity, $packed[$l->id] ?? '0', 4)) }} left)</span></span>
                                            <input type="number" step="any" min="0" wire:model="contents.{{ $l->id }}" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></label>
                                    @endforeach
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-button type="submit" size="sm" variant="secondary">Add carton</x-button>
                                    <x-button size="sm" wire:click="completePacking">Packing complete</x-button>
                                </div>
                            </form>
                        @endcan
                    @endif
                    @if ($d->status === 'packed') @can('packing_lists.edit') <div class="border-t border-line p-3"><x-button size="sm" variant="ghost" wire:click="reopenPacking">Reopen packing</x-button></div> @endcan @endif
                </x-card>
            @endif
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$d" :key="'a-'.$d->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Customer"><a href="{{ route('people.customers.show', $d->customer_id) }}" wire:navigate class="text-link hover:underline">{{ $d->customer->label() }}</a></x-people::detail>
                    <x-people::detail label="Shipping address" :value="$d->shipping_address" />
                    <x-people::detail label="Shipment" :value="trim($d->shipment_method.' · '.$d->vehicle, ' ·')" />
                    <x-people::detail label="Tracking number" :value="$d->tracking_number" />
                    <x-people::detail label="Notes" :value="$d->notes" />
                    @if ($d->cancel_reason) <x-people::detail label="Cancel reason" :value="$d->cancel_reason" /> @endif
                </dl>
            </x-card>
            <x-card title="Linked documents">
                <ul class="space-y-1 text-sm">
                    @foreach ($invoices as $inv)<li>Invoice <a href="{{ route('delivery.invoices.show', $inv) }}" wire:navigate class="font-mono text-link hover:underline">{{ $inv->number }}</a> <x-status :status="$inv->status" /></li>@endforeach
                    @foreach ($returns as $r)<li>Return <a href="{{ route('delivery.returns.show', $r) }}" wire:navigate class="font-mono text-link hover:underline">{{ $r->number }}</a> · @qty($r->quantity) <x-status :status="$r->status" /></li>@endforeach
                    @if ($invoices->isEmpty() && $returns->isEmpty())<li class="text-subtle">None yet.</li>@endif
                </ul>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$d" :key="'n-'.$d->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$d" :key="'h-'.$d->id" /></x-card>
        </div>
    </div>
</div>
