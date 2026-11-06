<x-print.layout :title="$title" heading="Purchase Order" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Vendor</strong><br>{{ $order->vendor->name }}<br>{!! nl2br(e((string) $order->vendor->address)) !!}<br>{{ $order->vendor->phone }} @if ($order->vendor->ntn) · NTN {{ $order->vendor->ntn }} @endif</td>
            <td><strong>PO No:</strong> {{ $order->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($order->order_date) }}<br><strong>Expected:</strong> {{ \App\Support\Format::date($order->expected_date) }}<br><strong>Terms:</strong> {{ $order->payment_terms }}</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>#</th><th>Item</th><th class="num">Qty</th><th>Unit</th><th class="num">Rate</th><th class="num">Amount</th><th>Expected</th></tr>
        @foreach ($order->lines as $i => $l)
            <tr><td>{{ $i + 1 }}</td><td>{{ $l->variant->fullLabel() }}@if ($l->description)<br><small>{{ $l->description }}</small>@endif</td>
                <td class="num">{{ \App\Support\Format::qty($l->quantity) }}</td><td>{{ $l->unit->symbol }}</td>
                <td class="num">{{ \App\Support\Format::money($l->rate, null, 4) }}</td><td class="num">{{ \App\Support\Format::money($l->amount) }}</td>
                <td>{{ \App\Support\Format::date($l->expected_date) }}</td></tr>
        @endforeach
        <tr class="totals"><td colspan="5">Total PKR</td><td class="num">{{ \App\Support\Format::money($order->total) }}</td><td></td></tr>
    </table>
    @if ($order->notes)<p><strong>Notes:</strong> {{ $order->notes }}</p>@endif
    <x-slot:signatures>Prepared by|Approved by|Vendor acceptance</x-slot:signatures>
</x-print.layout>
