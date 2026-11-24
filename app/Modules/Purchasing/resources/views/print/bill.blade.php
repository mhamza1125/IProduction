<x-print.layout :title="$title" heading="Purchase Invoice" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>Vendor</strong><br>{{ $bill->vendor->name }}@if ($bill->vendor->address)<br>{!! nl2br(e((string) $bill->vendor->address)) !!}@endif<br><strong>Vendor invoice:</strong> {{ $bill->vendor_invoice_number }}</td>
            <td><strong>Bill No:</strong> {{ $bill->number }}<br><strong>Date:</strong> {{ \App\Support\Format::date($bill->bill_date) }}<br><strong>Due:</strong> {{ \App\Support\Format::date($bill->due_date) }}<br><strong>Status:</strong> {{ \Illuminate\Support\Str::headline($bill->status) }}</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>#</th><th>Item</th><th>Goods receipt</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr>
        @foreach ($bill->lines as $i => $l)
            <tr><td>{{ $i + 1 }}</td><td>{{ $l->variant->fullLabel() }}</td><td>{{ $l->receiptLine?->receipt?->number }}</td>
                <td class="num">{{ \App\Support\Format::qty($l->quantity) }} {{ $l->variant->item->stockUnit->symbol }}</td>
                <td class="num">{{ \App\Support\Format::money($l->rate, null, 4) }}</td><td class="num">{{ \App\Support\Format::money($l->amount) }}</td></tr>
        @endforeach
        <tr><td colspan="5" class="num">Goods</td><td class="num">{{ \App\Support\Format::money($bill->goods_amount) }}</td></tr>
        @if ((float) $bill->tax_amount)<tr><td colspan="5" class="num">Tax</td><td class="num">{{ \App\Support\Format::money($bill->tax_amount) }}</td></tr>@endif
        @if ((float) $bill->charges_amount)<tr><td colspan="5" class="num">Charges</td><td class="num">{{ \App\Support\Format::money($bill->charges_amount) }}</td></tr>@endif
        @if ((float) $bill->deductions_amount)<tr><td colspan="5" class="num">Deductions</td><td class="num">−{{ \App\Support\Format::money($bill->deductions_amount) }}</td></tr>@endif
        <tr class="totals"><td colspan="5">Total PKR</td><td class="num">{{ \App\Support\Format::money($bill->total) }}</td></tr>
        @if ((float) $bill->paid_amount)<tr><td colspan="5" class="num">Paid</td><td class="num">{{ \App\Support\Format::money($bill->paid_amount) }}</td></tr>
            <tr><td colspan="5" class="num">Balance</td><td class="num">{{ \App\Support\Format::money(\App\Support\Decimal::sub($bill->total, $bill->paid_amount)) }}</td></tr>@endif
    </table>
    @if ($bill->notes)<p><strong>Notes:</strong> {{ $bill->notes }}</p>@endif
    <x-slot:signatures>Checked by|Approved by|Accounts</x-slot:signatures>
</x-print.layout>
