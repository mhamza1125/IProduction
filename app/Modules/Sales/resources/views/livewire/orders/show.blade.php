<div x-data="{ ask: null }">
    <x-page-header :title="'Sales order '.$order->number" :description="$order->customer->name.' · '.\App\Support\Format::date($order->order_date).($order->customer_po_number ? ' · PO '.$order->customer_po_number : '')">
        <x-status :status="$order->status" />
        @if ($order->sells_rejected_stock) <x-badge color="red">Rejected stock sale</x-badge> @endif
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($order->isDraft())
            @can('sales_orders.edit') <x-button variant="secondary" :href="route('sales.orders.edit', $order)" icon="pencil">Edit</x-button> @endcan
            @can('sales_orders.approve') <x-button wire:click="approve" wire:confirm="Approve {{ $order->number }}?">Approve</x-button> @endcan
            @can('sales_orders.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan
        @elseif ($order->isOpen())
            @can('sales_orders.override') <x-button variant="secondary" :href="route('sales.orders.edit', $order)" icon="pencil">Edit (approved)</x-button> @endcan
            @can('planning.view') <x-button :href="route('sales.planning', ['order' => $order->id])">Plan</x-button> @endcan
            @if (\Illuminate\Support\Facades\Route::has('delivery.deliveries.create')) @can('deliveries.create') <x-button variant="secondary" :href="route('delivery.deliveries.create', ['customer' => $order->customer_id])">Deliver</x-button> @endcan @endif
            @if ($order->status === 'approved') @can('sales_orders.approve') <x-button variant="ghost" x-on:click="ask = 'draft'">Return to draft</x-button> @endcan @endif
            @can('sales_orders.cancel')
                <x-button variant="ghost" x-on:click="ask = 'cancel'">Cancel order</x-button>
                <x-button variant="ghost" x-on:click="ask = 'close'">Close remaining</x-button>
            @endcan
        @endif
        @if (! $order->isDraft() && \Illuminate\Support\Facades\Route::has('reports.order-cost'))
            @can('cost.view') <x-button variant="ghost" :href="route('reports.order-cost', $order)">Order cost</x-button> @endcan
        @endif
        @if (! $order->isDraft())
            @can('sales_orders.print') <a href="{{ route('sales.orders.print', $order) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium text-muted hover:bg-hover"><x-icon name="printer" class="size-4" /> Print</a> @endcan
        @endif
        <div x-show="ask" x-cloak class="mt-2 flex w-full flex-wrap items-end gap-2 rounded-md bg-sunken p-3">
            <x-input label="Reason" wire:model="reason" class="min-w-64 flex-1" />
            <x-button x-show="ask === 'cancel'" variant="danger" wire:click="cancel" x-on:click="ask = null">Cancel order</x-button>
            <x-button x-show="ask === 'close'" variant="danger" wire:click="close" x-on:click="ask = null">Close remaining</x-button>
            <x-button x-show="ask === 'draft'" wire:click="returnToDraft" x-on:click="ask = null">Return to draft</x-button>
            <x-button variant="ghost" x-on:click="ask = null">Close</x-button>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Lines" :padding="false">
                <x-table>
                    <x-slot:head>
                        <x-table.th>Item</x-table.th><x-table.th align="right">Ordered</x-table.th><x-table.th align="right">Price</x-table.th>
                        <x-table.th align="right">Reserved</x-table.th><x-table.th align="right">On PO</x-table.th><x-table.th align="right">In production</x-table.th>
                        <x-table.th align="right">Delivered</x-table.th><x-table.th align="right">Invoiced</x-table.th><x-table.th align="right">Returned</x-table.th><x-table.th align="right">Remaining</x-table.th>
                    </x-slot:head>
                    @foreach ($lines as $l)
                        @php($p = $pictures[$l->id] ?? null)
                        <tr wire:key="l-{{ $l->id }}">
                            <x-table.td wide>{{ $l->variant->fullLabel() }} @if ($l->description)<div class="text-xs text-subtle">{{ $l->description }}</div>@endif @if ($p)<div class="text-xs text-warning">{{ $p['blocker'] }} → {{ $p['next'] }}</div>@endif</x-table.td>
                            <x-table.td align="right">@qty($l->quantity) {{ $l->variant->item->stockUnit->symbol }}</x-table.td>
                            <x-table.td align="right">@money($l->unit_price, null, 4)</x-table.td>
                            <x-table.td align="right">@qty($reserved[$l->id] ?? '0')</x-table.td>
                            <x-table.td align="right">@qty($p['on_order'] ?? '0')</x-table.td>
                            <x-table.td align="right">@qty($p['in_production'] ?? '0')</x-table.td>
                            <x-table.td align="right">@qty($l->delivered_qty)</x-table.td>
                            <x-table.td align="right">@qty($l->invoiced_qty)</x-table.td>
                            <x-table.td align="right">@qty($l->returned_qty)</x-table.td>
                            <x-table.td align="right" class="font-semibold">@qty($l->remaining()) @if ((float) $l->closed_qty)<div class="text-xs font-normal text-subtle">closed @qty($l->closed_qty)</div>@endif</x-table.td>
                        </tr>
                    @endforeach
                    <tr class="font-semibold"><x-table.td colspan="2">Total</x-table.td><x-table.td colspan="8" align="right">@money($order->total, $order->currency_code)</x-table.td></tr>
                </x-table>
            </x-card>
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$order" :key="'a-'.$order->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Customer"><a href="{{ route('people.customers.show', $order->customer_id) }}" wire:navigate class="text-link hover:underline">{{ $order->customer->label() }}</a></x-people::detail>
                    <x-people::detail label="Due date" :value="\App\Support\Format::date($order->due_date)" />
                    <x-people::detail label="Payment terms" :value="$order->payment_terms" />
                    <x-people::detail label="Shipping address" :value="$order->shipping_address" />
                    <x-people::detail label="Notes" :value="$order->notes" />
                    @if ($order->close_reason) <x-people::detail label="Close reason" :value="$order->close_reason" /> @endif
                    @if ($order->cancel_reason) <x-people::detail label="Cancel reason" :value="$order->cancel_reason" /> @endif
                </dl>
            </x-card>
            <x-card title="Linked supply">
                <ul class="space-y-1 text-sm">
                    @forelse ($linked as $a)
                        <li>@if ($a->supply && method_exists($a->supply, 'order'))<a href="{{ route('purchasing.orders.show', $a->supply->purchase_order_id) }}" wire:navigate class="font-mono text-link hover:underline">{{ $a->supply->order->number }}</a>@else {{ class_basename($a->supply_type) }} @endif · @qty($a->quantity) {{ $a->variant->sku }} <x-status :status="$a->status" /></li>
                    @empty
                        <li class="text-subtle">None yet.</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$order" :key="'n-'.$order->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$order" :key="'h-'.$order->id" /></x-card>
        </div>
    </div>
</div>
