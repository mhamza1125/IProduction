<x-print.layout :title="$title" heading="Commercial Invoice" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Bill to</strong><br>{{ $invoice->customer->name }}@if ($invoice->customer->company)<br>{{ $invoice->customer->company }}@endif<br>{!! nl2br(e((string) $invoice->customer->billing_address)) !!}<br>{{ $invoice->customer->country }}</td>
            <td><strong>Invoice No:</strong> {{ $invoice->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($invoice->invoice_date) }}<br><strong>Due:</strong> {{ \App\Support\Format::date($invoice->due_date) }}<br><strong>Terms:</strong> {{ $invoice->payment_terms }}<br><strong>Currency:</strong> {{ $invoice->currency_code }}</td>
        </tr></table>
    </x-slot:meta>
    @php($hasTax = $invoice->lines->contains(fn ($l) => $l->tax_rate !== null))
    <table class="lines">
        <tr><th>#</th><th>Item</th><th>Order · delivery</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Discount</th>@if ($hasTax)<th class="num">Tax</th>@endif<th class="num">Amount</th></tr>
        @foreach ($invoice->lines as $i => $l)
            <tr><td>{{ $i + 1 }}</td><td>{{ $l->variant->fullLabel() }}@if ($l->description)<br><small>{{ $l->description }}</small>@endif</td>
                <td>{{ $l->orderLine->order->number }}@if ($l->orderLine->order->customer_po_number) (PO {{ $l->orderLine->order->customer_po_number }})@endif · {{ $l->deliveryLine->delivery->number }}</td>
                <td class="num">{{ \App\Support\Format::qty($l->quantity) }} {{ $l->variant->item->stockUnit->symbol }}</td><td class="num">{{ \App\Support\Format::money($l->unit_price, null, 4) }}</td>
                <td class="num">{{ $l->discount ? \App\Support\Format::money($l->discount) : '' }}</td>
                @if ($hasTax)<td class="num">{{ $l->tax_rate !== null ? (float) $l->tax_rate.'% '.\App\Support\Format::money($l->tax_amount) : '' }}</td>@endif
                <td class="num">{{ \App\Support\Format::money($l->amount) }}</td></tr>
        @endforeach
        @php($span = $hasTax ? 7 : 6)
        <tr><td colspan="{{ $span }}" class="num">Subtotal</td><td class="num">{{ \App\Support\Format::money($invoice->subtotal) }}</td></tr>
        @if ((float) $invoice->discount_total)<tr><td colspan="{{ $span }}" class="num">Discount</td><td class="num">−{{ \App\Support\Format::money($invoice->discount_total) }}</td></tr>@endif
        @if ((float) $invoice->tax_total)<tr><td colspan="{{ $span }}" class="num">Tax</td><td class="num">{{ \App\Support\Format::money($invoice->tax_total) }}</td></tr>@endif
        <tr class="totals"><td colspan="{{ $span }}">Total {{ $invoice->currency_code }}</td><td class="num">{{ \App\Support\Format::money($invoice->total) }}</td></tr>
    </table>
    @if ($invoice->notes)<p><strong>Notes:</strong> {{ $invoice->notes }}</p>@endif
    <x-slot:signatures>Prepared by|Authorised signature</x-slot:signatures>
</x-print.layout>
