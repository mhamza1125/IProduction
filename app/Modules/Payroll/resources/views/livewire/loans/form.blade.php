<div>
    <x-page-header :title="$loan ? 'Edit '.$loan->number : 'New employee loan'" description="Posting pays the loan from the account. Payroll deducts the installment every month from the start month until the balance is zero." />
    <form wire:submit="save" data-save-shortcut>
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-select label="Employee" wire:model="employee_id" :options="$employees" placeholder="Choose…" required />
                <x-date-input label="Loan date" wire:model="loan_date" required />
                <x-input label="Amount (PKR)" type="number" step="any" min="0" wire:model="amount" required />
                <x-select label="Paid from" wire:model="money_account_id" :options="$accounts" required />
                <x-input label="Monthly installment" type="number" step="any" min="0" wire:model="installment" required />
                <x-input label="Start month" type="month" wire:model="start_month" required />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="ghost" :href="$loan ? route('payroll.loans.show', $loan) : route('payroll.loans.index')">Cancel</x-button>
                <x-button type="submit">Save draft</x-button>
            </div>
        </x-card>
    </form>
</div>
