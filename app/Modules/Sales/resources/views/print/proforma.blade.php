@php($c = $order->customer)
<x-print.layout :title="$title" heading="Proforma Invoice" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Buyer</strong><br>{{ $c->name }}@if ($c->company)<br>{{ $c->company }}@endif<br>{!! nl2br(e((string) ($order->shipping_address ?? $c->billing_address))) !!}<br>{{ $c->country }}@if ($c->ntn)<br>NTN / Tax ID: {{ $c->ntn }}@endif</td>
            <td><strong>Proforma No:</strong> {{ $order->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date(now()) }}<br><strong>Customer PO:</strong> {{ $order->customer_po_number }}<br><strong>Payment terms:</strong> {{ $order->payment_terms ?: $c->payment_terms }}<br><strong>Currency:</strong> {{ $order->currency_code }}
                @if ($c->default_incoterm)<br><strong>Incoterm:</strong> {{ trim($c->default_incoterm.' '.$c->default_incoterm_place) }}@endif
                @if ($c->default_port_of_discharge)<br><strong>Port of discharge:</strong> {{ $c->default_port_of_discharge }}@endif
                @if ($order->due_date)<br><strong>Shipment by:</strong> {{ \App\Support\Format::date($order->due_date) }}@endif</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>#</th><th>Item</th><th>HS code</th><th>Origin</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr>
        @foreach ($order->lines as $i => $l)
            <tr><td>{{ $i + 1 }}</td><td>{{ $l->variant->fullLabel() }}@if ($l->description)<br><small>{{ $l->description }}</small>@endif</td>
                <td>{{ $l->variant->item->hs_code }}</td><td>{{ $l->variant->item->origin_country ?: $country }}</td>
                <td class="num">{{ \App\Support\Format::qty($l->quantity) }} {{ $l->variant->item->stockUnit->symbol }}</td>
                <td class="num">{{ \App\Support\Format::money($l->unit_price, null, 4) }}</td><td class="num">{{ \App\Support\Format::money($l->line_total) }}</td></tr>
        @endforeach
        <tr class="totals"><td colspan="6">Total {{ $order->currency_code }}</td><td class="num">{{ \App\Support\Format::money($order->total) }}</td></tr>
    </table>
    <p><small>This is a proforma invoice for the buyer's approval and payment arrangements. It is not a demand for payment and has no tax or accounting effect; the commercial invoice is issued on shipment.</small></p>
    @if ($order->notes)<p><strong>Notes:</strong> {{ $order->notes }}</p>@endif
    <x-print.trade-terms :valid-until="$validUntil" />
    <x-slot:signatures>Prepared by|Authorised signature|Buyer's acceptance</x-slot:signatures>
</x-print.layout>
