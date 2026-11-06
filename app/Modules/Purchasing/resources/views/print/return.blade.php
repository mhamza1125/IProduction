<x-print.layout :title="$title" heading="Purchase Return" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Vendor</strong><br>{{ $return->vendor->name }}<br><strong>Goods receipt:</strong> {{ $return->receipt->number }}<br><strong>Reason:</strong> {{ $return->reason }}</td>
            <td><strong>Return No:</strong> {{ $return->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($return->return_date) }}<br><strong>Replacement required:</strong> {{ $return->replacement_required ? 'Yes' : 'No' }}</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Item</th><th>Lot</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr>
        @foreach ($return->lines as $l)
            <tr><td>{{ $l->variant->fullLabel() }}</td><td>{{ $l->lot->lot_number }}</td><td class="num">{{ \App\Support\Format::qty($l->quantity) }} {{ $l->variant->item->stockUnit->symbol }}</td>
                <td class="num">{{ \App\Support\Format::money($l->rate, null, 4) }}</td><td class="num">{{ \App\Support\Format::money($l->amount) }}</td></tr>
        @endforeach
        <tr class="totals"><td colspan="4">Total PKR</td><td class="num">{{ \App\Support\Format::money($return->total) }}</td></tr>
    </table>
    <x-slot:signatures>Prepared by|Approved by|Received by vendor</x-slot:signatures>
</x-print.layout>
