<div>
    <x-page-header title="Material processing" description="Material converted into other material: a thaan cut into pieces, materials combined or treated.">
        @can('material_processes.create') <x-button :href="route('production.processes.create')" icon="plus">New process</x-button> @endcan
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number, process…" dates :export="auth()->user()->can('material_processes.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All statuses</option>@foreach (['draft', 'approved', 'posted', 'cancelled'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No material processing yet.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th sortable="process_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Process</x-table.th><x-table.th>Worker</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($processes as $p)
                <tr wire:key="mp-{{ $p->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('production.processes.show', $p) }}" wire:navigate class="text-link hover:underline">{{ $p->number }}</a></x-table.td>
                    <x-table.td>@date($p->process_date)</x-table.td><x-table.td>{{ $p->process_name }}</x-table.td>
                    <x-table.td>{{ $p->employee?->name ?? $p->contractor?->name }}</x-table.td><x-table.td><x-status :status="$p->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($processes->hasPages()) <div class="border-t border-line px-4 py-3">{{ $processes->links() }}</div> @endif
    </x-card>
</div>
