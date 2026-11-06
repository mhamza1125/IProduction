<x-print.layout :title="$title" heading="Sales Order" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Customer</strong><br>{{ $order->customer->name }}@if ($order->customer->company)<br>{{ $order->customer->company }}@endif<br>{!! nl2br(e((string) ($order->shipping_address ?? $order->customer->billing_address))) !!}<br>{{ $order->customer->country }}</td>
            <td><strong>Order No:</strong> {{ $order->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($order->order_date) }}<br><strong>Due:</strong> {{ \App\Support\Format::date($order->due_date) }}<br><strong>Customer PO:</strong> {{ $order->customer_po_number }}<br><strong>Terms:</strong> {{ $order->payment_terms }}</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>#</th><th>Item</th><th class="num">Qty</th><th class="num">Unit price ({{ $order->currency_code }})</th><th class="num">Amount</th><th>Due</th></tr>
        @foreach ($order->lines as $i => $l)
            <tr><td>{{ $i + 1 }}</td><td>{{ $l->variant->fullLabel() }}@if ($l->description)<br><small>{{ $l->description }}</small>@endif</td>
                <td class="num">{{ \App\Support\Format::qty($l->quantity) }} {{ $l->variant->item->stockUnit->symbol }}</td>
                <td class="num">{{ \App\Support\Format::money($l->unit_price, null, 4) }}</td><td class="num">{{ \App\Support\Format::money($l->line_total) }}</td>
                <td>{{ \App\Support\Format::date($l->due_date) }}</td></tr>
        @endforeach
        <tr class="totals"><td colspan="4">Total {{ $order->currency_code }}</td><td class="num">{{ \App\Support\Format::money($order->total) }}</td><td></td></tr>
    </table>
    @if ($order->notes)<p><strong>Notes:</strong> {{ $order->notes }}</p>@endif
    <x-slot:signatures>Prepared by|Approved by|Customer acceptance</x-slot:signatures>
</x-print.layout>
