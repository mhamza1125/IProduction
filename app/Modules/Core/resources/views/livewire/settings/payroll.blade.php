<div>
    <x-page-header title="Settings" description="How payroll calculates daily and hourly rates and attendance deductions." />
    @include('core::settings-tabs')

    <form wire:submit="save" data-save-shortcut class="max-w-2xl">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Salary day basis" type="number" min="1" max="31" wire:model="salary_day_basis" required help="Daily rate = monthly salary ÷ this. Default 30." />
                <x-input label="Daily hours" type="number" step="0.5" min="0.5" max="24" wire:model="daily_hours" required help="Hourly rate = daily rate ÷ this. Default 8." />
                <x-checkbox wire:model="deduct_unpaid_days" label="Deduct unpaid leave days" help="Unpaid days × daily rate." class="sm:col-span-2" />
                <x-checkbox wire:model="deduct_late_hours" label="Deduct late hours" help="Late hours × hourly rate." class="sm:col-span-2" />
                <p class="text-xs text-zinc-500 sm:col-span-2">Paid leave is never deducted. Calculated deductions can be edited on a draft payroll by users with permission, with a reason.</p>
            </div>
            @can('settings.edit')
                <x-slot:footer><x-button type="submit">Save</x-button></x-slot:footer>
            @endcan
        </x-card>
    </form>
</div>
