<div>
    <x-page-header title="Worker rates" description="Individual piece rates. A worker's own rate for a stage replaces the route's standard rate from its effective date; an item-specific rate beats an all-items rate. Work already recorded keeps the rate it was paid at.">
        @can('worker_rates.create')
            <x-button wire:click="create" icon="plus">New rate</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search worker, stage, item…" dates :export="auth()->user()->can('worker_rates.export')">
            <select wire:model.live="workerType" aria-label="Worker type" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">Employees and contractors</option><option value="employee">Employees</option><option value="contractor">Contractors</option>
            </select>
            <select wire:model.live="stageFilter" aria-label="Stage" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All stages</option>
                @foreach ($stages as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="active">Active</option><option value="inactive">Inactive</option><option value="">All</option>
            </select>
        </x-table.toolbar>
        <x-table empty="No individual rates. Everyone is paid the route's standard stage rate.">
            <x-slot:head>
                <x-table.th>Worker</x-table.th>
                <x-table.th>Stage</x-table.th>
                <x-table.th>Item</x-table.th>
                <x-table.th sortable="rate" :sort-field="$sortField" :sort-direction="$sortDirection" align="right">Rate per piece</x-table.th>
                <x-table.th sortable="effective_from" :sort-field="$sortField" :sort-direction="$sortDirection">From</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>
            @foreach ($rates as $r)
                <tr wire:key="wr-{{ $r->id }}">
                    <x-table.td class="font-medium text-fg">{{ $r->workerName() }} <x-badge :color="$r->worker_type === 'employee' ? 'blue' : 'amber'">{{ $r->worker_type === 'employee' ? 'Employee' : 'Contractor' }}</x-badge></x-table.td>
                    <x-table.td>{{ $r->stage->name }}</x-table.td>
                    <x-table.td wide>{{ $r->item ? $r->item->code.' · '.$r->item->name : 'All items' }}</x-table.td>
                    <x-table.td align="right">@qty($r->rate)</x-table.td>
                    <x-table.td>@date($r->effective_from)</x-table.td>
                    <x-table.td><x-status :status="$r->is_active ? 'active' : 'inactive'" /></x-table.td>
                    <x-table.td align="right">
                        @can('worker_rates.edit') <x-button variant="ghost" size="sm" wire:click="edit({{ $r->id }})">Edit</x-button> @endcan
                        @can('worker_rates.delete') <x-button variant="ghost" size="sm" wire:click="delete({{ $r->id }})" wire:confirm="Delete this rate? Work already recorded is not changed.">Delete</x-button> @endcan
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($rates->hasPages()) <div class="border-t border-line px-4 py-3">{{ $rates->links() }}</div> @endif
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit rate' : 'New rate'">
        <form wire:submit="save" id="rate-form" class="grid gap-4" data-save-shortcut>
            <x-select label="Worker type" wire:model.live="worker_type" :options="['employee' => 'Wage employee', 'contractor' => 'Contractor']" required />
            @if ($worker_type === 'employee')
                <x-select label="Employee" wire:model="employee_id" :options="$employees" placeholder="Choose…" required help="Only wage (piece-rate) employees are listed." />
            @else
                <x-select label="Contractor" wire:model="contractor_id" :options="$contractors" placeholder="Choose…" required />
            @endif
            <x-select label="Stage" wire:model="stage_id" :options="$stages" placeholder="Choose…" required />
            <x-select label="Item" wire:model="item_id" :options="$items" placeholder="All items" help="Leave as All items unless this worker is paid differently for one product." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Rate per piece (PKR)" wire:model="rate" inputmode="decimal" required />
                <x-date-input label="Effective from" wire:model="effective_from" required />
            </div>
            <x-input label="Notes" wire:model="notes" />
            <x-checkbox wire:model="is_active" label="Active" />
            <p class="text-xs text-subtle">To change a rate from a later date, add a new rate with that date instead of editing this one; the old rate stays on record for earlier work.</p>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="rate-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
