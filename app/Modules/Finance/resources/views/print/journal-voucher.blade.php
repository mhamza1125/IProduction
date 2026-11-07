<x-print.layout :title="$title" heading="Journal Voucher" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Description</strong><br>{{ $voucher->description }}</td>
            <td><strong>JV No:</strong> {{ $voucher->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($voucher->voucher_date) }}<br><strong>Currency:</strong> {{ $voucher->currency_code }}</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Account</th><th>Party</th><th>Memo</th><th class="num">Debit</th><th class="num">Credit</th></tr>
        @foreach ($voucher->lines as $l)
            <tr><td>{{ $l->moneyAccount?->name ?? ($l->account?->code.' '.$l->account?->name) }}</td><td>{{ $l->party?->getAttribute('name') }}@if ($l->deduct_from_payroll) (payroll deduction)@endif</td><td>{{ $l->memo }}</td>
                <td class="num">{{ (float) $l->debit ? \App\Support\Format::money($l->debit) : '' }}</td><td class="num">{{ (float) $l->credit ? \App\Support\Format::money($l->credit) : '' }}</td></tr>
        @endforeach
        <tr class="totals"><td colspan="3">Total</td><td class="num">{{ \App\Support\Format::money($voucher->total) }}</td><td class="num">{{ \App\Support\Format::money($voucher->total) }}</td></tr>
    </table>
    <x-slot:signatures>Prepared by|Checked by|Approved by</x-slot:signatures>
</x-print.layout>
