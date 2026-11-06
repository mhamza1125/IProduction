<div>
    <x-page-header :title="'Goods receipt '.$receipt->number" :description="$receipt->vendor->name.' · '.$receipt->order->number.' · '.\App\Support\Format::date($receipt->receipt_date)">
        @if ($receipt->isDraft())
            @can('goods_receipts.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan
        @endif
        @if ($receipt->isPosted())
            @can('vendor_bills.create') <x-button variant="secondary" :href="route('purchasing.bills.create', ['receipt' => $receipt->id])">Create bill</x-button> @endcan
            @can('purchase_returns.create') <x-button variant="secondary" :href="route('purchasing.returns.create', ['receipt' => $receipt->id])">Return goods</x-button> @endcan
        @endif
    </x-page-header>

    <div class="mb-6"><x-document-actions :document="$receipt" :edit-route="route('purchasing.receipts.edit', $receipt)" :print-route="route('purchasing.receipts.print', $receipt)" /></div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Lines" :padding="false">
                <x-table>
                    <x-slot:head>
                        <x-table.th>Item</x-table.th><x-table.th align="right">Received</x-table.th><x-table.th align="right">Accepted</x-table.th><x-table.th align="right">Rejected</x-table.th>
                        <x-table.th>Decision</x-table.th><x-table.th>Into</x-table.th><x-table.th>Lot</x-table.th>
                        @can('cost.view') <x-table.th align="right">Value</x-table.th> @endcan
                    </x-slot:head>
                    @foreach ($lines as $l)
                        <tr wire:key="l-{{ $l->id }}" class="align-top">
                            <x-table.td>{{ $l->variant->fullLabel() }}</x-table.td>
                            <x-table.td align="right">@qty($l->received_qty) {{ $l->orderLine->unit->symbol }}</x-table.td>
                            <x-table.td align="right">@qty($l->accepted_qty)</x-table.td>
                            <x-table.td align="right">@qty($l->rejected_qty) @if ((float) $l->rejected_qty)<div class="text-xs text-zinc-500">{{ $l->rejected_action === 'keep' ? 'kept' : 'returned' }} · {{ $l->rejectReason?->name }}</div>@endif</x-table.td>
                            <x-table.td class="text-xs">{{ $l->over_receipt_action ? \App\Modules\Purchasing\Models\GoodsReceiptLine::OVER_RECEIPT_ACTIONS[$l->over_receipt_action] : '' }} @if ((float) $l->extra_qty)<div class="text-zinc-500">extra @qty($l->extra_qty)</div>@endif</x-table.td>
                            <x-table.td class="text-xs">{{ $l->location->name }}@if ($l->stage) · {{ $l->stage->name }}@endif</x-table.td>
                            <x-table.td class="font-mono text-xs">@if ($l->lot)<a href="{{ route('inventory.lots.show', $l->lot) }}" wire:navigate class="text-brand-600 hover:underline">{{ $l->lot->lot_number }}</a>@endif</x-table.td>
                            @can('cost.view') <x-table.td align="right">@money($l->value)</x-table.td> @endcan
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$receipt" :key="'a-'.$receipt->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Linked documents">
                <ul class="space-y-1 text-sm">
                    <li><a href="{{ route('purchasing.orders.show', $receipt->purchase_order_id) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $receipt->order->number }}</a> purchase order</li>
                    @foreach ($bills as $b) <li><a href="{{ route('purchasing.bills.show', $b) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $b->number }}</a> <x-status :status="$b->status" /></li> @endforeach
                    @foreach ($returns as $r) <li><a href="{{ route('purchasing.returns.show', $r) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $r->number }}</a> <x-status :status="$r->status" /></li> @endforeach
                </ul>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$receipt" :key="'n-'.$receipt->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$receipt" :key="'h-'.$receipt->id" /></x-card>
        </div>
    </div>
</div>
