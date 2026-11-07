<x-card title="Amount cleared = deductions + net received" :padding="false">
    <x-table>
        <x-slot:head><x-table.th>Line</x-table.th><x-table.th align="right">{{ $doc->currency_code }}</x-table.th></x-slot:head>
        <tr><x-table.td>Net received into the account</x-table.td><x-table.td align="right">@money($doc->net_received)</x-table.td></tr>
        @foreach ($deductions as $d)<tr wire:key="d-{{ $d->id }}"><x-table.td>Deduction: {{ $d->account->name }}@if ($d->memo) · {{ $d->memo }}@endif</x-table.td><x-table.td align="right">@money($d->amount)</x-table.td></tr>@endforeach
        <tr class="font-semibold"><x-table.td>Cleared from the customer</x-table.td><x-table.td align="right">@money($doc->amount, $doc->currency_code)</x-table.td></tr>
    </x-table>
</x-card>
<x-card title="Invoices paid" :padding="false">
    <x-table empty="Not allocated to any invoice yet.">
        <x-slot:head><x-table.th>Invoice</x-table.th><x-table.th>Date</x-table.th><x-table.th>From advance</x-table.th><x-table.th align="right">Amount</x-table.th></x-slot:head>
        @if ($doc->isPosted())
            @foreach ($allocations as $a)
                <tr wire:key="al-{{ $a->id }}"><x-table.td class="font-mono text-xs">{{ $a->receivable?->getAttribute('number') }}</x-table.td><x-table.td>@date($a->allocation_date)</x-table.td><x-table.td>{{ $a->from_advance ? 'Yes' : '' }}</x-table.td><x-table.td align="right">@money($a->amount)</x-table.td></tr>
            @endforeach
        @else
            @foreach ($planned as $p)<tr><x-table.td class="text-xs">Planned · {{ $p['type'] }} #{{ $p['id'] }}</x-table.td><x-table.td></x-table.td><x-table.td></x-table.td><x-table.td align="right">@money($p['amount'])</x-table.td></tr>@endforeach
        @endif
    </x-table>
    @if ($doc->isPosted())
        <div class="border-t border-zinc-200 p-4 text-sm dark:border-zinc-800">
            Unallocated (customer advance): <span class="font-semibold">@money($doc->unallocated(), $doc->currency_code)</span>
            @if ($openInvoices->isNotEmpty())
                @can('receipts.post')
                    <form wire:submit="applyAdvance" class="mt-3 flex flex-wrap items-end gap-2">
                        <x-select label="Apply to invoice" wire:model="target" :options="$openInvoices" placeholder="Choose…" class="min-w-64" />
                        <x-input label="Amount" type="number" step="any" min="0" wire:model="apply_amount" />
                        <x-button type="submit" size="sm" variant="secondary">Apply</x-button>
                    </form>
                @endcan
            @endif
        </div>
    @endif
</x-card>
