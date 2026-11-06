<x-print.layout :title="$title" heading="Ledger" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta">
            <tr><td><strong>{{ $typeLabel }}:</strong> {{ $subject->code ?? '' }} {{ $subject->name }}</td>
                <td style="text-align:right"><strong>Period:</strong> {{ $from ? \App\Support\Format::date($from) : 'Start' }} to {{ $to ? \App\Support\Format::date($to) : 'Today' }}</td></tr>
            <tr><td><strong>Currency:</strong> {{ $currency }}</td><td></td></tr>
        </table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Date</th><th>Document</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr>
        <tr><td colspan="5"><strong>Opening balance</strong></td><td class="num">{{ \App\Support\Format::money($ledger['opening']) }}</td></tr>
        @foreach ($ledger['rows'] as $row)
            <tr>
                <td>{{ \App\Support\Format::date($row['line']->line_date) }}</td>
                <td>{{ $row['line']->journal->source?->number ?? $row['line']->journal->number }}</td>
                <td>{{ $row['line']->memo ?? $row['line']->journal->description }}</td>
                <td class="num">{{ (float) $row['line']->debit ? \App\Support\Format::money($row['line']->debit) : '' }}</td>
                <td class="num">{{ (float) $row['line']->credit ? \App\Support\Format::money($row['line']->credit) : '' }}</td>
                <td class="num">{{ \App\Support\Format::money($row['balance']) }}</td>
            </tr>
        @endforeach
        <tr class="totals"><td colspan="3">Closing balance</td><td class="num">{{ \App\Support\Format::money($ledger['debits']) }}</td><td class="num">{{ \App\Support\Format::money($ledger['credits']) }}</td><td class="num">{{ \App\Support\Format::money($ledger['closing']) }}</td></tr>
    </table>
</x-print.layout>
