<x-print.layout :title="$title" heading="Receipt Voucher" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Received from</strong><br>{{ $receipt->customer->name }}<br>{!! nl2br(e((string) $receipt->customer->billing_address)) !!}</td>
            <td><strong>Receipt No:</strong> {{ $receipt->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($receipt->receipt_date) }}<br><strong>Deposited to:</strong> {{ $receipt->moneyAccount->name }}<br><strong>Reference:</strong> {{ $receipt->reference }}</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Line</th><th class="num">{{ $receipt->currency_code }}</th></tr>
        <tr><td>Net received</td><td class="num">{{ \App\Support\Format::money($receipt->net_received) }}</td></tr>
        @foreach ($receipt->deductions as $d)<tr><td>Deduction: {{ $d->account->name }} {{ $d->memo }}</td><td class="num">{{ \App\Support\Format::money($d->amount) }}</td></tr>@endforeach
        <tr class="totals"><td>Amount cleared</td><td class="num">{{ \App\Support\Format::money($receipt->amount) }}</td></tr>
    </table>
    @if ($receipt->allocations->isNotEmpty())
        <table class="lines">
            <tr><th>Invoice</th><th class="num">Amount</th></tr>
            @foreach ($receipt->allocations as $a)<tr><td>{{ $a->receivable?->getAttribute('number') }}</td><td class="num">{{ \App\Support\Format::money($a->amount) }}</td></tr>@endforeach
            <tr class="totals"><td>Advance (unallocated)</td><td class="num">{{ \App\Support\Format::money($receipt->unallocated()) }}</td></tr>
        </table>
    @endif
    @if ($receipt->notes)<p><strong>Notes:</strong> {{ $receipt->notes }}</p>@endif
    <x-slot:signatures>Prepared by|Approved by|Received by</x-slot:signatures>
</x-print.layout>
