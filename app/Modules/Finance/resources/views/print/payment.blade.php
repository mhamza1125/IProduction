<x-print.layout :title="$title" heading="Payment Voucher" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Paid to</strong><br>{{ $payment->payee?->name ?? $payment->expenseAccount?->name }} ({{ \App\Modules\Finance\Models\Payment::KINDS[$payment->payee_kind] }})</td>
            <td><strong>Voucher No:</strong> {{ $payment->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($payment->payment_date) }}<br><strong>From:</strong> {{ $payment->moneyAccount->name }}<br><strong>Method:</strong> {{ $payment->method?->name }} {{ $payment->reference }}</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Against</th><th class="num">Amount (PKR)</th></tr>
        @foreach ($payment->allocations as $a)
            <tr><td>{{ $a->allocatable?->payableLabel() }}</td><td class="num">{{ \App\Support\Format::money($a->amount) }}</td></tr>
        @endforeach
        @if ($payment->payee_kind !== 'expense' && (float) $payment->unallocated() > 0)
            <tr><td>Advance</td><td class="num">{{ \App\Support\Format::money($payment->unallocated()) }}</td></tr>
        @endif
        @if ($payment->payee_kind === 'expense')
            <tr><td>{{ $payment->expenseAccount?->name }}</td><td class="num">{{ \App\Support\Format::money($payment->amount) }}</td></tr>
        @endif
        <tr class="totals"><td>Total</td><td class="num">{{ \App\Support\Format::money($payment->amount) }}</td></tr>
    </table>
    @if ($payment->notes)<p><strong>Notes:</strong> {{ $payment->notes }}</p>@endif
    <x-slot:signatures>Prepared by|Approved by|Received by</x-slot:signatures>
</x-print.layout>
