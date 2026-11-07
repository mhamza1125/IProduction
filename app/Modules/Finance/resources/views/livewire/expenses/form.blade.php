<div>
    <x-page-header :title="$expense ? 'Edit '.$expense->number : 'New expense'" description="Pay it now from cash or bank, or record it as payable to a vendor and pay it later with a Payment." />
    <form wire:submit="save" data-save-shortcut>
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-date-input label="Date" wire:model="expense_date" required />
                <x-select label="Expense head" wire:model="expense_account_id" :options="$heads" placeholder="Choose…" required class="lg:col-span-2" />
                <x-select label="Payment" wire:model.live="payment" :options="['now' => 'Paid now', 'payable' => 'Payable to a vendor']" />
                @if ($payment === 'now')
                    <x-select label="Paid from" wire:model="money_account_id" :options="$accounts" placeholder="Choose…" required />
                    <x-select label="Vendor (optional)" wire:model="vendor_id" :options="$vendors" placeholder="—" />
                @else
                    <x-select label="Payable to" wire:model="vendor_id" :options="$vendors" placeholder="Choose…" required class="lg:col-span-2" />
                @endif
                <x-input label="Amount (PKR)" type="number" step="any" min="0" wire:model="amount" required />
                <x-input label="Reference" wire:model="reference" />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="ghost" :href="$expense ? route('finance.expenses.show', $expense) : route('finance.expenses.index')">Cancel</x-button>
                <x-button type="submit">Save draft</x-button>
            </div>
        </x-card>
    </form>
</div>
