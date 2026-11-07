<x-print.layout :title="$title" heading="Product Travel Card" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Item</strong><br>{{ $ptc->variant->fullLabel() }}<br><strong>Planned:</strong> {{ \App\Support\Format::qty($ptc->planned_qty) }} {{ $ptc->variant->item->stockUnit->symbol }}<br><strong>Route:</strong> {{ $ptc->route->name }}@if ($ptc->orderLine)<br><strong>Order:</strong> {{ $ptc->orderLine->order->number }} · {{ $ptc->orderLine->order->customer->name }}@endif</td>
            <td><strong>PTC No:</strong> {{ $ptc->number }}<br><strong>Start:</strong> {{ \App\Support\Format::date($ptc->start_date) }}<br><strong>Target:</strong> {{ \App\Support\Format::date($ptc->target_date) }}<br><strong>Responsible:</strong> {{ $ptc->responsible?->name }}<br><strong>Output lot:</strong> {{ $ptc->outputLot?->lot_number }}@if ($ptc->parent)<br><strong>Parent:</strong> {{ $ptc->parent->number }}@endif</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>#</th><th>Stage</th><th class="num">Issued</th><th class="num">Received</th><th class="num">Rejected</th><th>Worker / sign</th><th>Date</th></tr>
        @foreach ($stages as $s)
            <tr><td>{{ $s->sequence }}</td><td>{{ $s->stage->name }}@if ($s->is_skipped) (skipped: {{ $s->skip_reason }})@endif</td>
                <td class="num">{{ \App\Support\Format::qty($s->issued_qty) }}</td><td class="num">{{ \App\Support\Format::qty($s->received_qty) }}</td><td class="num">{{ \App\Support\Format::qty($s->rejected_qty) }}</td>
                <td style="min-width:120px">{{ $transactions->where('ptc_stage_id', $s->id)->map->workerName()->filter()->unique()->implode(', ') }}</td><td></td></tr>
        @endforeach
    </table>
    @if ($reconciliation->isNotEmpty())
        <h3>Material</h3>
        <table class="lines">
            <tr><th>Material</th><th class="num">Expected</th><th class="num">Issued + extra</th><th class="num">Consumed</th><th class="num">Returned</th><th class="num">Wastage</th><th class="num">Held</th></tr>
            @php($names = $materials->keyBy('item_variant_id'))
            @foreach ($reconciliation as $r)
                <tr><td>{{ $names[$r['variant_id']]?->variant->fullLabel() ?? \App\Modules\Catalog\Models\ItemVariant::withTrashed()->find($r['variant_id'])?->fullLabel() }}</td>
                    <td class="num">{{ \App\Support\Format::qty($r['expected']) }}</td><td class="num">{{ \App\Support\Format::qty(\App\Support\Decimal::add($r['issued'], $r['extra'], 4)) }}</td>
                    <td class="num">{{ \App\Support\Format::qty($r['consumed']) }}</td><td class="num">{{ \App\Support\Format::qty($r['returned']) }}</td><td class="num">{{ \App\Support\Format::qty($r['wastage']) }}</td><td class="num">{{ \App\Support\Format::qty($r['held']) }}</td></tr>
            @endforeach
        </table>
    @endif
    <p><strong>Released to store:</strong> {{ \App\Support\Format::qty($ptc->released_qty) }}</p>
    @if ($ptc->notes)<p><strong>Notes:</strong> {{ $ptc->notes }}</p>@endif
    <x-slot:signatures>Production supervisor|Quality check|Store</x-slot:signatures>
</x-print.layout>
