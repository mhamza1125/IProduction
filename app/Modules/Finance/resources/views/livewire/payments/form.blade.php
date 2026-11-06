<div>
    <x-page-header :title="$payment ? 'Edit '.$payment->number : 'New payment'" description="Allocated amounts settle bills; anything left over stays as an advance." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card class="max-w-4xl">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-select label="Paid to" wire:model.live="payee_kind" :options="$kinds" required />
                @if ($payee_kind === 'expense')
                    <x-select label="Expense head" wire:model="expense_account_id" :options="$expenseAccounts" placeholder="Choose…" required class="sm:col-span-2" />
                @else
                    <x-select :label="$kinds[$payee_kind]" wire:model.live="payee_id" :options="$payees" placeholder="Choose…" required class="sm:col-span-2" />
                @endif
                <x-select label="From account" wire:model="money_account_id" :options="$accounts" required />
                <x-select label="Method" wire:model="payment_method_id" :options="$methods" placeholder="—" />
                <x-date-input label="Date" wire:model="payment_date" required />
                <x-input label="Amount (PKR)" type="number" step="0.01" min="0" wire:model="amount" required />
                <x-input label="Cheque / reference" wire:model="reference" />
                <x-input label="Notes" wire:model="notes" />
            </div>
        </x-card>

        @if ($payee_kind !== 'expense' && $payee_id)
            <x-card title="Allocate to open items" :padding="false">
                <x-slot:actions><x-button size="sm" variant="secondary" wire:click="payAllOpen">Pay all open</x-button></x-slot:actions>
                <x-table empty="Nothing open for this payee. The whole amount will be an advance.">
                    <x-slot:head><x-table.th>Document</x-table.th><x-table.th align="right">Open balance</x-table.th><x-table.th align="right">Pay now</x-table.th></x-slot:head>
                    @foreach ($open as $item)
                        @php($key = $item->getMorphClass().':'.$item->getKey())
                        <tr wire:key="o-{{ $key }}">
                            <x-table.td>{{ $item->payableLabel() }}</x-table.td>
                            <x-table.td align="right">@money($item->payableBalance())</x-table.td>
                            <x-table.td align="right"><input type="number" step="0.01" min="0" wire:model="allocations.{{ $key }}" aria-label="Amount" class="w-32 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"></x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        @endif

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="$payment ? route('finance.payments.show', $payment) : route('finance.payments.index')">Cancel</x-button>
            <x-button type="submit">Save draft</x-button>
        </div>
    </form>
</div>
