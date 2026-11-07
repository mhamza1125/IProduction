<div>
    <x-page-header :title="$advance ? 'Edit '.$advance->number : 'New salary advance'" description="Posting pays the advance from the account. Payroll deducts it in its salary month; any unused part carries forward." />
    <form wire:submit="save" data-save-shortcut>
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-select label="Employee" wire:model="employee_id" :options="$employees" placeholder="Choose…" required />
                <x-date-input label="Date" wire:model="advance_date" required />
                <x-input label="Amount (PKR)" type="number" step="any" min="0" wire:model="amount" required />
                <x-input label="Salary month" type="month" wire:model="salary_month" required />
                <x-select label="Paid from" wire:model="money_account_id" :options="$accounts" required />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="ghost" :href="$advance ? route('payroll.advances.show', $advance) : route('payroll.advances.index')">Cancel</x-button>
                <x-button type="submit">Save draft</x-button>
            </div>
        </x-card>
    </form>
</div>
