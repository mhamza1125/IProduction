<div>
    <x-page-header title="Attendance" description="Monthly summary per employee, entered by hand. Rows can be edited until the employee's payroll for the month is posted." />
    <form wire:submit="save" data-save-shortcut>
        <x-card :padding="false">
            <div class="flex flex-wrap items-end gap-3 border-b border-line px-4 py-3">
                <x-input label="Month" type="month" wire:model.live="month" />
                <x-select label="Department" wire:model.live="department" :options="$departments" placeholder="All departments" />
            </div>
            <x-table empty="No active employees.">
                <x-slot:head>
                    <x-table.th>Employee</x-table.th>
                    @foreach ($fields as $f => $label)<x-table.th align="right">{{ $label }}</x-table.th>@endforeach
                    <x-table.th>Notes</x-table.th>
                </x-slot:head>
                @foreach ($employees as $e)
                    @php($isLocked = $locked->has($e->id))
                    <tr wire:key="att-{{ $e->id }}">
                        <x-table.td class="whitespace-nowrap">{{ $e->name }}<div class="text-xs text-subtle">{{ $e->code }} · {{ ucfirst($e->employee_type) }}@if ($isLocked) · payroll posted @endif</div></x-table.td>
                        @foreach ($fields as $f => $label)
                            <x-table.td align="right"><input type="number" step="0.25" min="0" wire:model="rows.{{ $e->id }}.{{ $f }}" @disabled($isLocked) aria-label="{{ $label }} for {{ $e->name }}" class="w-20 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong disabled:bg-hover bg-surface"></x-table.td>
                        @endforeach
                        <x-table.td><input wire:model="rows.{{ $e->id }}.notes" @disabled($isLocked) aria-label="Notes for {{ $e->name }}" class="w-40 rounded-md border-0 py-1.5 text-sm ring-1 ring-line-strong disabled:bg-hover bg-surface"></x-table.td>
                    </tr>
                @endforeach
            </x-table>
            @can('attendance.edit') <div class="flex justify-end border-t border-line p-3"><x-button type="submit">Save attendance</x-button></div> @endcan
        </x-card>
    </form>
</div>
