@can('payroll.create')
    <x-card class="mb-6">
        <form wire:submit="createRun" class="flex flex-wrap items-end gap-3">
            <x-input label="Month" type="month" wire:model="newMonth" required />
            <x-select label="Department" wire:model="newDepartment" :options="$departments" placeholder="All departments" />
            <x-select label="Employee type" wire:model="newType" :options="\App\Modules\People\Models\Employee::TYPES" placeholder="All types" />
            <x-button type="submit" icon="plus">Create payroll</x-button>
        </form>
    </x-card>
@endcan
