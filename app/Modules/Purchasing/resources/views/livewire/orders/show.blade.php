<div x-data="{ ask: null }">
    <x-page-header :title="'Purchase order '.$order->number" :description="$order->vendor->name.' · '.\App\Support\Format::date($order->order_date)">
        <x-status :status="$order->status" />
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($order->isDraft())
            @can('purchase_orders.edit') <x-button variant="secondary" :href="route('purchasing.orders.edit', $order)" icon="pencil">Edit</x-button> @endcan
            @can('purchase_orders.approve') <x-button wire:click="approve" wire:confirm="Approve {{ $order->number }}?">Approve</x-button> @endcan
            @can('purchase_orders.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft {{ $order->number }}?">Delete</x-button> @endcan
        @endif
        @if ($order->status === 'approved')
            @can('purchase_orders.print') <x-button variant="secondary" wire:click="markSent">Mark as sent</x-button> @endcan
            @can('purchase_orders.approve') <x-button variant="ghost" x-on:click="ask = 'draft'">Return to draft</x-button> @endcan
        @endif
        @if ($order->isOpen())
            @can('goods_receipts.create') <x-button :href="route('purchasing.receipts.create', ['order' => $order->id])" icon="inbox">Receive goods</x-button> @endcan
        @endif
        @if (in_array($order->status, ['draft', 'approved', 'sent'], true) && $receipts->whereNotNull('posted_at')->isEmpty())
            @can('purchase_orders.cancel') <x-button variant="ghost" x-on:click="ask = 'cancel'">Cancel order</x-button> @endcan
        @elseif ($order->status === 'partly_received')
            @can('purchase_orders.cancel') <x-button variant="ghost" x-on:click="ask = 'short'">Close short</x-button> @endcan
        @endif
        @if (! $order->isDraft())
            @can('purchase_orders.print') <a href="{{ route('purchasing.orders.print', $order) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium text-muted hover:bg-hover"><x-icon name="printer" class="size-4" /> Print</a> @endcan
        @endif

        <div x-show="ask" x-cloak class="mt-2 flex w-full flex-wrap items-end gap-2 rounded-md bg-sunken p-3">
            <x-input label="Reason" wire:model="reason" class="min-w-64 flex-1" />
            <x-button x-show="ask === 'cancel'" variant="danger" wire:click="cancel" x-on:click="ask = null">Cancel order</x-button>
            <x-button x-show="ask === 'short'" variant="danger" wire:click="closeShort" x-on:click="ask = null">Close remaining short</x-button>
            <x-button x-show="ask === 'draft'" wire:click="returnToDraft" x-on:click="ask = null">Return to draft</x-button>
            <x-button variant="ghost" x-on:click="ask = null">Close</x-button>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Lines" :padding="false">
                <x-table>
                    <x-slot:head>
                        <x-table.th>Item</x-table.th><x-table.th align="right">Ordered</x-table.th><x-table.th align="right">Rate</x-table.th><x-table.th align="right">Amount</x-table.th>
                        <x-table.th align="right">Received</x-table.th><x-table.th align="right">Accepted</x-table.th><x-table.th align="right">Rejected</x-table.th>
                        <x-table.th align="right">Returned</x-table.th><x-table.th align="right">Billed</x-table.th><x-table.th align="right">Outstanding</x-table.th>
                    </x-slot:head>
                    @foreach ($lines as $l)
                        <tr wire:key="l-{{ $l->id }}">
                            <x-table.td wide>{{ $l->variant->fullLabel() }} @if ($l->stage)<x-badge>{{ $l->stage->name }}</x-badge>@endif @if ($l->description)<div class="text-xs text-subtle">{{ $l->description }}</div>@endif</x-table.td>
                            <x-table.td align="right">@qty($l->quantity) {{ $l->unit->symbol }}</x-table.td>
                            <x-table.td align="right">@money($l->rate, null, 4)</x-table.td>
                            <x-table.td align="right">@money($l->amount)</x-table.td>
                            <x-table.td align="right">@qty($l->received_qty)</x-table.td>
                            <x-table.td align="right">@qty($l->accepted_qty)</x-table.td>
                            <x-table.td align="right">@qty($l->rejected_qty)</x-table.td>
                            <x-table.td align="right">@qty($l->returned_qty)</x-table.td>
                            <x-table.td align="right">@qty($l->billed_qty)</x-table.td>
                            <x-table.td align="right" class="font-semibold">@qty($l->outstanding()) @if ((float) $l->closed_short_qty)<div class="text-xs font-normal text-subtle">closed @qty($l->closed_short_qty)</div>@endif</x-table.td>
                        </tr>
                    @endforeach
                    <tr class="font-semibold"><x-table.td colspan="3">Total PKR</x-table.td><x-table.td align="right">@money($order->total)</x-table.td><x-table.td colspan="6"></x-table.td></tr>
                </x-table>
            </x-card>
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$order" :key="'a-'.$order->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Vendor"><a href="{{ route('people.vendors.show', $order->vendor_id) }}" wire:navigate class="text-link hover:underline">{{ $order->vendor->label() }}</a></x-people::detail>
                    <x-people::detail label="Expected" :value="\App\Support\Format::date($order->expected_date)" />
                    <x-people::detail label="Payment terms" :value="$order->payment_terms" />
                    <x-people::detail label="Notes" :value="$order->notes" />
                    @if ($order->cancel_reason) <x-people::detail label="Cancel reason" :value="$order->cancel_reason" /> @endif
                </dl>
            </x-card>
            <x-card title="Linked documents">
                <ul class="space-y-1 text-sm">
                    @foreach ($receipts as $r) <li><a href="{{ route('purchasing.receipts.show', $r) }}" wire:navigate class="font-mono text-link hover:underline">{{ $r->number }}</a> <x-status :status="$r->status" /></li> @endforeach
                    @foreach ($bills as $b) <li><a href="{{ route('purchasing.bills.show', $b) }}" wire:navigate class="font-mono text-link hover:underline">{{ $b->number }}</a> <x-status :status="$b->status" /></li> @endforeach
                    @foreach ($returns as $r) <li><a href="{{ route('purchasing.returns.show', $r) }}" wire:navigate class="font-mono text-link hover:underline">{{ $r->number }}</a> <x-status :status="$r->status" /></li> @endforeach
                    @if ($receipts->isEmpty()) <li class="text-subtle">None yet.</li> @endif
                </ul>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$order" :key="'n-'.$order->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$order" :key="'h-'.$order->id" /></x-card>
        </div>
    </div>
</div>
