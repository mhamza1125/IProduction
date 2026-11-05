<x-people::profile :record="$employee" resource="employees" :subtitle="\App\Modules\People\Models\Employee::TYPES[$employee->employee_type] ?? null"
                   :edit-route="route('people.employees.edit', $employee)" :index-route="route('people.employees.index')">
    <x-slot:details>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-people::detail label="Code" :value="$employee->code" />
            <x-people::detail label="Father name" :value="$employee->father_name" />
            <x-people::detail label="CNIC" :value="$employee->cnic" />
            <x-people::detail label="Phone" :value="$employee->phone" />
            <x-people::detail label="Department" :value="$employee->department?->name" />
            <x-people::detail label="Designation" :value="$employee->designation?->name" />
            <x-people::detail label="Type" :value="\App\Modules\People\Models\Employee::TYPES[$employee->employee_type] ?? ''" />
            <x-people::detail label="Join date" :value="\App\Support\Format::date($employee->join_date)" />
            <x-people::detail label="Exit date" :value="\App\Support\Format::date($employee->exit_date)" />
            @can('salary.view')
                <x-people::detail label="Monthly salary" :value="\App\Support\Format::money($employee->monthly_salary, 'PKR')" />
            @endcan
            <x-people::detail label="Payment method" :value="$employee->paymentMethod?->name" />
            <x-people::detail label="Bank" :value="collect([$employee->bank_name, $employee->bank_account_title, $employee->bank_account_number, $employee->iban])->filter()->implode(' · ')" class="sm:col-span-2" />
            <x-people::detail label="Address" :value="$employee->address" class="sm:col-span-2 lg:col-span-3" />
        </dl>
    </x-slot:details>
</x-people::profile>
