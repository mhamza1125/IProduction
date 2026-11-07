<x-print.layout :title="$title" heading="Salary Slip" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr>
            <td style="width:55%"><strong>{{ $line->employee->name }}</strong> ({{ $line->employee->code }})<br>{{ $line->employee->designation?->name }} @if ($line->employee->department) · {{ $line->employee->department->name }}@endif<br>{{ ucfirst($line->employee_type) }} employee</td>
            <td><strong>Month:</strong> {{ $run->monthLabel() }}<br><strong>Payroll:</strong> {{ $run->number }}@if ($line->monthly_salary)<br><strong>Monthly salary:</strong> {{ \App\Support\Format::money($line->monthly_salary) }}@endif</td>
        </tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Line</th><th class="num">PKR</th></tr>
        @foreach ($line->items as $item)
            <tr><td>{{ \App\Modules\Payroll\Models\PayrollLineItem::KINDS[$item->kind] ?? $item->kind }}: {{ $item->description }}</td><td class="num">{{ \App\Support\Format::money($item->amount) }}</td></tr>
        @endforeach
        <tr class="totals"><td>Net payable</td><td class="num">{{ \App\Support\Format::money($line->net_payable) }}</td></tr>
        @if ((float) $line->carried_forward)<tr><td>Carried to next month</td><td class="num">{{ \App\Support\Format::money($line->carried_forward) }}</td></tr>@endif
        @if ($line->isPosted())<tr><td>Paid so far</td><td class="num">{{ \App\Support\Format::money($line->paid_amount) }}</td></tr>@endif
    </table>
    <x-slot:signatures>Prepared by|Employee signature</x-slot:signatures>
</x-print.layout>
