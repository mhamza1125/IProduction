<x-print.layout :title="$title" heading="Goods Receipt" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Vendor</strong><br>{{ $receipt->vendor->name }}<br><strong>PO:</strong> {{ $receipt->order->number }}<br><strong>Vendor delivery note:</strong> {{ $receipt->vendor_delivery_note }}</td>
            <td><strong>GRN No:</strong> {{ $receipt->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($receipt->receipt_date) }}<br><strong>Received by:</strong> {{ $receipt->receiver?->name }}<br><strong>Status:</strong> {{ \Illuminate\Support\Str::headline($receipt->status) }}</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Item</th><th class="num">Ordered</th><th class="num">Received</th><th class="num">Accepted</th><th class="num">Rejected</th><th>Lot</th><th>Location</th></tr>
        @foreach ($receipt->lines as $l)
            <tr><td>{{ $l->variant->fullLabel() }}</td><td class="num">{{ \App\Support\Format::qty($l->ordered_qty) }} {{ $l->orderLine->unit->symbol }}</td>
                <td class="num">{{ \App\Support\Format::qty($l->received_qty) }}</td><td class="num">{{ \App\Support\Format::qty($l->accepted_qty) }}</td>
                <td class="num">{{ \App\Support\Format::qty($l->rejected_qty) }}</td><td>{{ $l->lot?->lot_number }}</td><td>{{ $l->location->name }}</td></tr>
        @endforeach
    </table>
    @if ($receipt->notes)<p><strong>Notes:</strong> {{ $receipt->notes }}</p>@endif
    <x-slot:signatures>Received by|Checked by|Store in-charge</x-slot:signatures>
</x-print.layout>
