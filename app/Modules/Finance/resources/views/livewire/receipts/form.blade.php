<div>
    <x-page-header :title="$receipt ? 'Edit '.$receipt->number : 'New customer receipt'" description="Everything stays in the customer's currency. Amount cleared from the customer = deductions + net received. Anything not set against an invoice stays as an advance." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-select label="Customer" wire:model.live="customer_id" :options="$customers" placeholder="Choose…" required class="lg:col-span-2" />
                <x-date-input label="Date" wire:model="receipt_date" required />
                <x-input :label="'Amount cleared'.($customer ? ' ('.$customer->currency_code.')' : '')" type="number" step="any" min="0" wire:model.blur="amount" required />
                <x-select label="Deposit account" wire:model="money_account_id" :options="$accounts" :placeholder="$customer ? 'Choose a '.$customer->currency_code.' account…' : 'Choose a customer first'" required class="lg:col-span-2" />
                <x-input label="Reference" wire:model="reference" />
                <div class="text-sm"><span class="block font-medium text-zinc-700 dark:text-zinc-300">Net received</span><span class="mt-2 block text-lg font-semibold tabular">{{ $net !== null ? \App\Support\Format::money($net, $customer?->currency_code) : '—' }}</span></div>
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-4" />
            </div>
        </x-card>
        <x-card title="Deductions (tax, bank charges, commission…)" :padding="false">
            <x-table empty="No deductions.">
                <x-slot:head><x-table.th>Head</x-table.th><x-table.th align="right">Amount</x-table.th><x-table.th>Memo</x-table.th><x-table.th></x-table.th></x-slot:head>
                @foreach ($deductions as $i => $d)
                    <tr wire:key="dd-{{ $i }}">
                        <x-table.td><x-select wire:model="deductions.{{ $i }}.gl_account_id" :options="$heads" placeholder="Choose…" aria-label="Head" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model.blur="deductions.{{ $i }}.amount" aria-label="Amount" /></x-table.td>
                        <x-table.td><x-input wire:model="deductions.{{ $i }}.memo" aria-label="Memo" /></x-table.td>
                        <x-table.td><x-button size="sm" variant="ghost" icon="trash" wire:click="removeDeduction({{ $i }})" aria-label="Remove" /></x-table.td>
                    </tr>
                @endforeach
            </x-table>
            <div class="border-t border-zinc-200 p-3 dark:border-zinc-800"><x-button size="sm" variant="secondary" icon="plus" wire:click="addDeduction">Add deduction</x-button></div>
        </x-card>
        @if ($customer)
            <x-card title="Pay invoices" :padding="false">
                <x-table empty="No open invoices in this currency.">
                    <x-slot:head><x-table.th>Invoice</x-table.th><x-table.th align="right">Balance</x-table.th><x-table.th align="right">Pay now</x-table.th></x-slot:head>
                    @foreach ($invoices as $inv)
                        @php($key = $inv->getMorphClass().':'.$inv->getKey())
                        <tr wire:key="inv-{{ $key }}">
                            <x-table.td>{{ $inv->receivableLabel() }}</x-table.td>
                            <x-table.td align="right">@money($inv->receivableBalance(), $customer->currency_code)</x-table.td>
                            <x-table.td align="right"><input type="number" step="any" min="0" wire:model="allocations.{{ $key }}" aria-label="Amount" class="w-32 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"></x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif
        <div class="flex justify-end gap-2">
            <x-button variant="ghost" :href="$receipt ? route('finance.receipts.show', $receipt) : route('finance.receipts.index')">Cancel</x-button>
            <x-button type="submit">Save draft</x-button>
        </div>
    </form>
</div>
