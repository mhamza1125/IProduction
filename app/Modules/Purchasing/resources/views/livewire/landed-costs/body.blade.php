<x-card title="Charges" :padding="false">
    <x-table>
        <x-slot:head><x-table.th>Charge</x-table.th><x-table.th>Description</x-table.th><x-table.th align="right">Amount (PKR)</x-table.th></x-slot:head>
        @foreach ($charges as $c)
            <tr><x-table.td>{{ $types[$c->charge_type] ?? $c->charge_type }}</x-table.td><x-table.td wide>{{ $c->description }}</x-table.td><x-table.td align="right">@money($c->amount)</x-table.td></tr>
        @endforeach
    </x-table>
</x-card>

<x-card title="Goods receipts" :padding="false">
    <x-table>
        <x-slot:head><x-table.th>Receipt</x-table.th><x-table.th>Date</x-table.th><x-table.th>Vendor</x-table.th><x-table.th align="right">Goods value (PKR)</x-table.th></x-slot:head>
        @foreach ($receipts as $r)
            <tr>
                <x-table.td class="font-mono"><a href="{{ route('purchasing.receipts.show', $r) }}" wire:navigate class="text-link hover:underline">{{ $r->number }}</a></x-table.td>
                <x-table.td>@date($r->receipt_date)</x-table.td><x-table.td>{{ $r->order->vendor->name }}</x-table.td><x-table.td align="right">@money($r->total_value)</x-table.td>
            </tr>
        @endforeach
    </x-table>
</x-card>

@if ($allocations->isNotEmpty())
    <x-card title="How the charges were added" :padding="false">
        <x-table>
            <x-slot:head>
                <x-table.th>Item · lot</x-table.th><x-table.th align="right">Share</x-table.th>
                <x-table.th align="right">On stock</x-table.th><x-table.th align="right">Of which in production</x-table.th><x-table.th align="right">Already used or sold</x-table.th>
                @can('cost.view') <x-table.th align="right">Unit cost before → after</x-table.th> @endcan
            </x-slot:head>
            @foreach ($allocations as $a)
                <tr>
                    <x-table.td wide>{{ $a->receiptLine->variant->sku }} · <a href="{{ route('inventory.lots.show', $a->lot) }}" wire:navigate class="font-mono text-link hover:underline">{{ $a->lot->lot_number }}</a>
                        <div class="text-xs text-subtle">@qty($a->on_hand_qty) of @qty($a->lot_qty) still in stock when posted</div></x-table.td>
                    <x-table.td align="right">@money($a->amount)</x-table.td>
                    <x-table.td align="right">@money($a->to_stock)</x-table.td>
                    <x-table.td align="right">@money($a->to_production)</x-table.td>
                    <x-table.td align="right">@money($a->to_cogs)</x-table.td>
                    @can('cost.view') <x-table.td align="right" class="whitespace-nowrap">@money($a->unit_cost_before, null, 4) → @money($a->unit_cost_after, null, 4)</x-table.td> @endcan
                </tr>
            @endforeach
        </x-table>
    </x-card>
@endif
