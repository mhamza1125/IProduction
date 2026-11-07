<x-print.layout :title="$title" heading="Contractor Job" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Contractor</strong><br>{{ $job->contractor->name }}<br>{{ $job->contractor->phone }}<br>{!! nl2br(e((string) $job->contractor->address)) !!}</td>
            <td><strong>Job No:</strong> {{ $job->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($job->job_date) }}<br><strong>Due:</strong> {{ \App\Support\Format::date($job->due_date) }}@if ($job->orderLine)<br><strong>Order:</strong> {{ $job->orderLine->order->number }}@endif</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>#</th><th>Output</th><th>Stage</th><th class="num">Agreed</th><th class="num">Rate (PKR)</th><th class="num">Received</th><th class="num">Accepted</th><th class="num">Rejected</th></tr>
        @foreach ($job->lines as $i => $l)
            <tr><td>{{ $i + 1 }}</td><td>{{ $l->variant->fullLabel() }}</td><td>{{ $l->stage?->name ?? 'Finished' }}</td><td class="num">{{ \App\Support\Format::qty($l->quantity) }}</td>
                <td class="num">{{ \App\Support\Format::money($l->rate, null, 4) }}</td><td class="num">{{ \App\Support\Format::qty($l->received_qty) }}</td><td class="num">{{ \App\Support\Format::qty($l->accepted_qty) }}</td><td class="num">{{ \App\Support\Format::qty($l->rejected_qty) }}</td></tr>
        @endforeach
    </table>
    @if ($reconciliation->isNotEmpty())
        <h3>Material</h3>
        <table class="lines">
            <tr><th>Material</th><th class="num">Expected</th><th class="num">Issued</th><th class="num">Used</th><th class="num">Returned</th><th class="num">Wastage</th><th class="num">Held</th></tr>
            @foreach ($reconciliation as $r)
                <tr><td>{{ $r['variant']->fullLabel() }}</td><td class="num">{{ \App\Support\Format::qty($r['expected']) }}</td><td class="num">{{ \App\Support\Format::qty($r['issued']) }}</td><td class="num">{{ \App\Support\Format::qty($r['consumed']) }}</td>
                    <td class="num">{{ \App\Support\Format::qty($r['returned']) }}</td><td class="num">{{ \App\Support\Format::qty($r['wastage']) }}</td><td class="num">{{ \App\Support\Format::qty($r['held']) }}</td></tr>
            @endforeach
        </table>
    @endif
    @if ($job->notes)<p><strong>Notes:</strong> {{ $job->notes }}</p>@endif
    <x-slot:signatures>Issued by|Contractor|Received by</x-slot:signatures>
</x-print.layout>
