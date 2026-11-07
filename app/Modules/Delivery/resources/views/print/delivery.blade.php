<x-print.layout :title="$title" heading="Delivery Note" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Deliver to</strong><br>{{ $delivery->customer->name }}<br>{!! nl2br(e((string) $delivery->shipping_address)) !!}<br>{{ $delivery->customer->country }}</td>
            <td><strong>Delivery No:</strong> {{ $delivery->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($delivery->delivery_date) }}<br><strong>Shipment:</strong> {{ $delivery->shipment_method }}<br><strong>Vehicle / courier:</strong> {{ $delivery->vehicle }}<br><strong>Tracking:</strong> {{ $delivery->tracking_number }}</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>#</th><th>Order</th><th>Item</th><th>Lot</th><th class="num">Quantity</th></tr>
        @foreach ($delivery->lines as $i => $l)
            <tr><td>{{ $i + 1 }}</td><td>{{ $l->orderLine->order->number }}@if ($l->orderLine->order->customer_po_number)<br><small>PO {{ $l->orderLine->order->customer_po_number }}</small>@endif</td><td>{{ $l->variant->fullLabel() }}</td>
                <td>{{ $l->allocations->map(fn ($a) => $a->lot->lot_number)->implode(', ') }}</td><td class="num">{{ \App\Support\Format::qty($l->quantity) }} {{ $l->variant->item->stockUnit->symbol }}</td></tr>
        @endforeach
        <tr class="totals"><td colspan="4">Total pieces</td><td class="num">{{ \App\Support\Format::qty(\App\Support\Decimal::sum($delivery->lines->pluck('quantity'), 4)) }}</td></tr>
    </table>
    @if ($delivery->notes)<p><strong>Notes:</strong> {{ $delivery->notes }}</p>@endif
    <x-slot:signatures>Prepared by|Dispatched by|Received by (customer)</x-slot:signatures>
</x-print.layout>
