<div>
    <x-page-header title="Employees" description="In-house staff. Salary employees are paid monthly; wage employees for recorded work.">
        @can('employees.create')
            <x-button :href="route('people.employees.create')" icon="plus">New employee</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search code, name, CNIC, phone…" dates :export="auth()->user()->can('employees.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="">All</option>
            </select>
            <select wire:model.live="type" aria-label="Type" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All types</option>
                @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="department" aria-label="Department" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All departments</option>
                @foreach ($departments as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
            </select>
        </x-table.toolbar>

        <x-table empty="No employees found.">
            <x-slot:head>
                <x-table.th sortable="code" :sort-field="$sortField" :sort-direction="$sortDirection">Code</x-table.th>
                <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Name</x-table.th>
                <x-table.th sortable="employee_type" :sort-field="$sortField" :sort-direction="$sortDirection">Type</x-table.th>
                <x-table.th>Department</x-table.th>
                <x-table.th>Designation</x-table.th>
                <x-table.th sortable="join_date" :sort-field="$sortField" :sort-direction="$sortDirection">Joined</x-table.th>
                @can('salary.view') <x-table.th align="right">Salary</x-table.th> @endcan
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($employees as $e)
                <tr wire:key="e-{{ $e->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <x-table.td class="font-mono"><a href="{{ route('people.employees.show', $e) }}" wire:navigate class="text-brand-600 hover:underline dark:text-brand-500">{{ $e->code }}</a></x-table.td>
                    <x-table.td class="font-medium">{{ $e->name }}</x-table.td>
                    <x-table.td>{{ $types[$e->employee_type] ?? $e->employee_type }}</x-table.td>
                    <x-table.td>{{ $e->department?->name }}</x-table.td>
                    <x-table.td>{{ $e->designation?->name }}</x-table.td>
                    <x-table.td class="whitespace-nowrap">@date($e->join_date)</x-table.td>
                    @can('salary.view') <x-table.td align="right">@money($e->monthly_salary)</x-table.td> @endcan
                    <x-table.td><x-status :status="$e->is_active ? 'active' : 'inactive'" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($employees->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $employees->links() }}</div>
        @endif
    </x-card>
</div>
