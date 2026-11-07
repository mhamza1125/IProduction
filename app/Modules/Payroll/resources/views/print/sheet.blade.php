<x-print.layout :title="$title" :heading="'Payroll sheet '.$run->monthLabel()" :for-pdf="$forPdf">
    <x-slot:meta>
        <table class="meta"><tr><td><strong>Payroll:</strong> {{ $run->number }} · {{ $run->department?->name ?? 'All departments' }}</td><td><strong>Status:</strong> {{ \Illuminate\Support\Str::headline($run->status) }}</td></tr></table>
    </x-slot:meta>
    <table class="lines">
        <tr><th>Employee</th><th class="num">Salary / work</th><th class="num">OT + allow.</th><th class="num">Attendance</th><th class="num">Fines</th><th class="num">Loan</th><th class="num">Advance</th><th class="num">B/F</th><th class="num">Net</th><th>Signature</th></tr>
        @foreach ($lines as $l)
            <tr><td>{{ $l->employee->name }} ({{ $l->employee->code }})</td><td class="num">{{ \App\Support\Format::money($l->earnings) }}</td><td class="num">{{ \App\Support\Format::money(\App\Support\Decimal::add($l->overtime, $l->allowances)) }}</td>
                <td class="num">{{ \App\Support\Format::money($l->attendance_deduction) }}</td><td class="num">{{ \App\Support\Format::money($l->fines) }}</td><td class="num">{{ \App\Support\Format::money($l->loan_installment) }}</td>
                <td class="num">{{ \App\Support\Format::money($l->advance_deduction) }}</td><td class="num">{{ \App\Support\Format::money($l->brought_forward) }}</td><td class="num"><strong>{{ \App\Support\Format::money($l->net_payable) }}</strong></td><td style="min-width:90px"></td></tr>
        @endforeach
        <tr class="totals"><td colspan="8">Total net payable (PKR)</td><td class="num">{{ \App\Support\Format::money($run->total_net) }}</td><td></td></tr>
    </table>
    <x-slot:signatures>Prepared by (HR)|Checked by (Accounts)|Approved by</x-slot:signatures>
</x-print.layout>
