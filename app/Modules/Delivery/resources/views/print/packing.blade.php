<x-print.layout :title="$title" heading="Packing List" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Consignee</strong><br>{{ $delivery->customer->name }}<br>{!! nl2br(e((string) $delivery->shipping_address)) !!}</td>
            <td><strong>Delivery No:</strong> {{ $delivery->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($delivery->delivery_date) }}<br><strong>Cartons:</strong> {{ $totals['cartons'] }}<br><strong>Pieces:</strong> {{ \App\Support\Format::qty($totals['pieces']) }}<br><strong>Gross / net:</strong> {{ $totals['gross'] }} / {{ $totals['net'] }} kg</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Carton</th><th>Type · size (cm)</th><th>Order</th><th>Item</th><th class="num">Qty</th><th class="num">Gross kg</th><th class="num">Net kg</th><th>Marks</th></tr>
        @foreach ($delivery->packingList?->cartons ?? [] as $c)
            @foreach ($c->lines as $j => $cl)
                <tr>
                    @if ($j === 0)<td rowspan="{{ $c->lines->count() }}">{{ $c->carton_number }}</td><td rowspan="{{ $c->lines->count() }}">{{ $c->carton_type }}@if ($c->length) {{ (float) $c->length }}×{{ (float) $c->width }}×{{ (float) $c->height }}@endif</td>@endif
                    <td>{{ $cl->deliveryLine->orderLine->order->number }}</td><td>{{ $cl->deliveryLine->variant->fullLabel() }}</td><td class="num">{{ \App\Support\Format::qty($cl->quantity) }}</td>
                    @if ($j === 0)<td rowspan="{{ $c->lines->count() }}" class="num">{{ $c->gross_weight !== null ? (float) $c->gross_weight : '' }}</td><td rowspan="{{ $c->lines->count() }}" class="num">{{ $c->net_weight !== null ? (float) $c->net_weight : '' }}</td><td rowspan="{{ $c->lines->count() }}">{{ $c->marks }}</td>@endif
                </tr>
            @endforeach
        @endforeach
        <tr class="totals"><td colspan="4">{{ $totals['cartons'] }} cartons</td><td class="num">{{ \App\Support\Format::qty($totals['pieces']) }}</td><td class="num">{{ $totals['gross'] }}</td><td class="num">{{ $totals['net'] }}</td><td></td></tr>
    </table>
    <x-slot:signatures>Packed by|Checked by</x-slot:signatures>
</x-print.layout>
