<x-print.layout :title="$title" heading="Delivery Return" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Customer</strong><br>{{ $return->customer->name }}</td>
            <td><strong>Return No:</strong> {{ $return->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($return->return_date) }}<br><strong>Delivery:</strong> {{ $return->deliveryLine->delivery->number }}<br><strong>Order:</strong> {{ $return->deliveryLine->orderLine->order->number }}@if ($return->invoiceLine)<br><strong>Invoice:</strong> {{ $return->invoiceLine->invoice->number }}@endif</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Item</th><th>Lot</th><th class="num">Quantity</th><th>Reason</th><th>Replacement</th></tr>
        <tr><td>{{ $return->deliveryLine->variant->fullLabel() }}</td><td>{{ $return->lot->lot_number }}</td><td class="num">{{ \App\Support\Format::qty($return->quantity) }}</td><td>{{ $return->reason }}</td><td>{{ $return->replacement_required ? 'Required' : 'No' }}</td></tr>
    </table>
    @if ($return->notes)<p><strong>Notes:</strong> {{ $return->notes }}</p>@endif
    <x-slot:signatures>Received by|Inspected by</x-slot:signatures>
</x-print.layout>
