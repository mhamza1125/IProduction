<div>
    <x-page-header :title="$employee ? 'Edit employee' : 'New employee'" :description="$employee?->code" />

    <form wire:submit="save" data-save-shortcut class="max-w-4xl space-y-6">
        <x-card title="Employee">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Code" wire:model="code" :help="$employee ? null : 'Leave empty to number automatically.'" />
                <x-select label="Employee type" wire:model.live="employee_type" :options="$types" required help="Salary: fixed monthly. Wage: paid for recorded work." />
                <x-input label="Name" wire:model="name" required autofocus />
                <x-input label="Father name" wire:model="father_name" />
                <x-input label="CNIC" wire:model="cnic" placeholder="35202-1234567-1" />
                <x-input label="Phone" wire:model="phone" />
                <x-textarea label="Address" wire:model="address" rows="2" class="sm:col-span-2" />
                <x-select label="Department" wire:model="department_id" :options="$departments" placeholder="—" />
                <x-select label="Designation" wire:model="designation_id" :options="$designations" placeholder="—" />
                <x-date-input label="Join date" wire:model="join_date" required />
                <x-date-input label="Exit date" wire:model="exit_date" />
            </div>
        </x-card>

        <x-card title="Pay">
            <div class="grid gap-4 sm:grid-cols-2">
                @if ($canSeeSalary)
                    <x-input label="Monthly salary (PKR)" type="number" step="0.01" min="0" wire:model="monthly_salary"
                             :required="$employee_type === 'salary'" :help="$employee_type === 'wage' ? 'Optional for wage employees.' : null" />
                @endif
                <x-select label="Payment method" wire:model="payment_method_id" :options="$paymentMethods" placeholder="—" />
                <x-input label="Bank name" wire:model="bank_name" />
                <x-input label="Account title" wire:model="bank_account_title" />
                <x-input label="Account number" wire:model="bank_account_number" />
                <x-input label="IBAN" wire:model="iban" />
            </div>
        </x-card>

        <x-card>
            <x-checkbox wire:model="is_active" label="Active" />
            <x-slot:footer>
                <x-button variant="secondary" :href="$employee ? route('people.employees.show', $employee) : route('people.employees.index')">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
