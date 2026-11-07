<x-print.layout :title="$title" :heading="$report->title()" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr><td>@foreach ($report->filters() as $f)@if (! empty($filters[$f->key]))<strong>{{ $f->label }}:</strong> {{ $f->type === 'date' ? \App\Support\Format::date($filters[$f->key]) : ($f->options[$filters[$f->key]] ?? $filters[$f->key]) }} &nbsp; @endif @endforeach</td><td><strong>Printed:</strong> {{ \App\Support\Format::datetime(now()) }}</td></tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr>@foreach ($report->columns() as $c)<th @class(['num' => $c->isNumeric()])>{{ $c->label }}</th>@endforeach</tr>
        @foreach ($rows as $row)
            <tr>@foreach ($report->columns() as $c)<td @class(['num' => $c->isNumeric()])>{{ \App\Modules\Reports\Support\ReportFormatter::display($c, $row) }}</td>@endforeach</tr>
        @endforeach
        @foreach (collect($totals)->flatMap(fn ($byCur) => array_keys($byCur))->unique() as $currency)
            <tr class="totals">@foreach ($report->columns() as $j => $c)<td @class(['num' => $c->isNumeric()])>@if ($j === 0)Total {{ $currency }}@elseif (isset($totals[$c->key][$currency])){{ $c->type === 'qty' ? \App\Support\Format::qty($totals[$c->key][$currency]) : \App\Support\Format::money($totals[$c->key][$currency]) }}@endif</td>@endforeach</tr>
        @endforeach
    </table>
    @if ($report->note())<p><small>{{ $report->note() }}</small></p>@endif
</x-print.layout>
