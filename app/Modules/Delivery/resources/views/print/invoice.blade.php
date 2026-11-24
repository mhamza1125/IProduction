@php
    $country = (string) app(\App\Modules\Core\Services\Settings::class)->get('company.country');
    $export = $invoice->incoterm || $invoice->lines->contains(fn ($l) => $l->variant->item->hs_code);
    $hasTax = $invoice->lines->contains(fn ($l) => $l->tax_rate !== null);
    $shipping = array_filter([
        'Incoterm' => $invoice->incoterm ? trim($invoice->incoterm.' '.$invoice->incoterm_place) : null,
        'Port of loading' => $invoice->port_of_loading, 'Port of discharge' => $invoice->port_of_discharge, 'Final destination' => $invoice->final_destination,
        'Vessel / flight' => $invoice->vessel_flight, 'Container no.' => $invoice->container_no,
        'BL / AWB' => $invoice->bl_awb_no ? $invoice->bl_awb_no.($invoice->bl_awb_date ? ' · '.\App\Support\Format::date($invoice->bl_awb_date) : '') : null,
        'FI number' => $invoice->fi_number,
        'GD number' => $invoice->gd_number ? $invoice->gd_number.($invoice->gd_date ? ' · '.\App\Support\Format::date($invoice->gd_date) : '') : null,
    ]);
@endphp
<x-print.layout :title="$title" heading="Commercial Invoice" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Buyer</strong><br>{{ $invoice->customer->name }}@if ($invoice->customer->company)<br>{{ $invoice->customer->company }}@endif<br>{!! nl2br(e((string) $invoice->customer->billing_address)) !!}<br>{{ $invoice->customer->country }}@if ($invoice->customer->ntn)<br>NTN / Tax ID: {{ $invoice->customer->ntn }}@endif
                @if ($invoice->consignee)<br><br><strong>Consignee</strong><br>{!! nl2br(e($invoice->consignee)) !!}@endif
                @if ($invoice->notify_party)<br><br><strong>Notify party</strong><br>{!! nl2br(e($invoice->notify_party)) !!}@endif</td>
            <td><strong>Invoice No:</strong> {{ $invoice->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($invoice->invoice_date) }}<br><strong>Due:</strong> {{ \App\Support\Format::date($invoice->due_date) }}<br><strong>Terms:</strong> {{ $invoice->payment_terms }}<br><strong>Currency:</strong> {{ $invoice->currency_code }}@if ($invoice->incoterm)<br><strong>Incoterm:</strong> {{ trim($invoice->incoterm.' '.$invoice->incoterm_place) }}@endif</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>#</th><th>Item</th>@if ($export)<th>HS code</th><th>Origin</th>@endif<th>Order · delivery</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Discount</th>@if ($hasTax)<th class="num">Tax</th>@endif<th class="num">Amount</th></tr>
        @foreach ($invoice->lines as $i => $l)
            <tr><td>{{ $i + 1 }}</td><td>{{ $l->variant->fullLabel() }}@if ($l->description)<br><small>{{ $l->description }}</small>@endif</td>
                @if ($export)<td>{{ $l->variant->item->hs_code }}</td><td>{{ $l->variant->item->origin_country ?: $country }}</td>@endif
                <td>{{ $l->orderLine->order->number }}@if ($l->orderLine->order->customer_po_number) (PO {{ $l->orderLine->order->customer_po_number }})@endif · {{ $l->deliveryLine->delivery->number }}</td>
                <td class="num">{{ \App\Support\Format::qty($l->quantity) }} {{ $l->variant->item->stockUnit->symbol }}</td><td class="num">{{ \App\Support\Format::money($l->unit_price, null, 4) }}</td>
                <td class="num">{{ $l->discount ? \App\Support\Format::money($l->discount) : '' }}</td>
                @if ($hasTax)<td class="num">{{ $l->tax_rate !== null ? (float) $l->tax_rate.'% '.\App\Support\Format::money($l->tax_amount) : '' }}</td>@endif
                <td class="num">{{ \App\Support\Format::money($l->amount) }}</td></tr>
        @endforeach
        @php($span = 6 + ($hasTax ? 1 : 0) + ($export ? 2 : 0))
        <tr><td colspan="{{ $span }}" class="num">Subtotal</td><td class="num">{{ \App\Support\Format::money($invoice->subtotal) }}</td></tr>
        @if ((float) $invoice->discount_total)<tr><td colspan="{{ $span }}" class="num">Discount</td><td class="num">−{{ \App\Support\Format::money($invoice->discount_total) }}</td></tr>@endif
        @if ((float) $invoice->tax_total)<tr><td colspan="{{ $span }}" class="num">Tax</td><td class="num">{{ \App\Support\Format::money($invoice->tax_total) }}</td></tr>@endif
        <tr class="totals"><td colspan="{{ $span }}">Total {{ $invoice->currency_code }}</td><td class="num">{{ \App\Support\Format::money($invoice->total) }}</td></tr>
    </table>
    @if ($shipping !== [])
        <table class="meta" style="margin-top:10px">
            @foreach (array_chunk($shipping, 2, true) as $pair)
                <tr>@foreach ($pair as $label => $value)<td style="width:50%"><strong>{{ $label }}:</strong> {{ $value }}</td>@endforeach</tr>
            @endforeach
        </table>
    @endif
    @if ($invoice->notes)<p><strong>Notes:</strong> {{ $invoice->notes }}</p>@endif
    <x-print.trade-terms :marks="$invoice->shipping_marks" :origin-statement="$export" />
    <x-slot:signatures>Prepared by|Authorised signature</x-slot:signatures>
</x-print.layout>
