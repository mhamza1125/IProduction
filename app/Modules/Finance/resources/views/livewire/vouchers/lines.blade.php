<x-card title="Lines" :padding="false">
    <x-table>
        <x-slot:head><x-table.th>Account</x-table.th><x-table.th>Party</x-table.th><x-table.th>Memo</x-table.th><x-table.th align="right">Debit</x-table.th><x-table.th align="right">Credit</x-table.th></x-slot:head>
        @foreach ($lines as $l)
            <tr wire:key="vl-{{ $l->id }}">
                <x-table.td>{{ $l->moneyAccount?->name ?? ($l->account?->code.' '.$l->account?->name) }}</x-table.td>
                <x-table.td>{{ $l->party?->getAttribute('name') }}@if ($l->deduct_from_payroll)<x-badge color="amber">deduct from payroll</x-badge>@endif</x-table.td>
                <x-table.td class="text-xs">{{ $l->memo }}</x-table.td>
                <x-table.td align="right">@if ((float) $l->debit)@money($l->debit)@endif</x-table.td>
                <x-table.td align="right">@if ((float) $l->credit)@money($l->credit)@endif</x-table.td>
            </tr>
        @endforeach
        <tr class="font-semibold"><x-table.td colspan="3">Total</x-table.td><x-table.td align="right">@money($doc->total)</x-table.td><x-table.td align="right">@money($doc->total)</x-table.td></tr>
    </x-table>
</x-card>
