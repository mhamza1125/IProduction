<x-print.layout :title="$title" heading="Credit Note" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Customer</strong><br>{{ $note->customer->name }}<br>{!! nl2br(e((string) $note->customer->billing_address)) !!}</td>
            <td><strong>Credit Note No:</strong> {{ $note->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($note->credit_date) }}@if ($note->invoice)<br><strong>Invoice:</strong> {{ $note->invoice->number }}@endif<br><strong>Currency:</strong> {{ $note->currency_code }}</td>
        </tr></table>
    </x-slot:meta>
    <p><strong>Reason:</strong> {{ $note->reason }}</p>
    <table class="lines">
        <tr><th>#</th><th>Description</th><th class="num">Amount</th><th class="num">Tax</th></tr>
        @foreach ($note->lines as $i => $l)
            <tr><td>{{ $i + 1 }}</td><td>{{ $l->description }}</td><td class="num">{{ \App\Support\Format::money($l->amount) }}</td><td class="num">{{ $l->tax_rate !== null ? (float) $l->tax_rate.'% '.\App\Support\Format::money($l->tax_amount) : '' }}</td></tr>
        @endforeach
        <tr class="totals"><td colspan="3">Total {{ $note->currency_code }}</td><td class="num">{{ \App\Support\Format::money($note->total) }}</td></tr>
    </table>
    <x-slot:signatures>Prepared by|Authorised signature</x-slot:signatures>
</x-print.layout>
