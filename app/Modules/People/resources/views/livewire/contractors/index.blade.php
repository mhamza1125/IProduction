<div>
    <x-page-header title="Contractors" description="External businesses or people who take jobs. Contractor ledgers are in PKR.">
        @can('contractors.create')
            <x-button :href="route('people.contractors.create')" icon="plus">New contractor</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search code, name, contact, work type…" dates :export="auth()->user()->can('contractors.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="">All</option>
            </select>
        </x-table.toolbar>

        <x-table empty="No contractors found.">
            <x-slot:head>
                <x-table.th sortable="code" :sort-field="$sortField" :sort-direction="$sortDirection">Code</x-table.th>
                <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Name</x-table.th>
                <x-table.th>Work types</x-table.th>
                <x-table.th>Contact</x-table.th>
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($contractors as $c)
                <tr wire:key="c-{{ $c->id }}" class="hover:bg-hover">
                    <x-table.td class="font-mono"><a href="{{ route('people.contractors.show', $c) }}" wire:navigate class="text-link hover:underline">{{ $c->code }}</a></x-table.td>
                    <x-table.td class="font-medium">{{ $c->name }}</x-table.td>
                    <x-table.td>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($c->work_types ?? [] as $type) <x-badge>{{ $type }}</x-badge> @endforeach
                        </div>
                    </x-table.td>
                    <x-table.td class="text-subtle">{{ collect([$c->contact_person, $c->phone])->filter()->implode(' · ') }}</x-table.td>
                    <x-table.td><x-status :status="$c->is_active ? 'active' : 'inactive'" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($contractors->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $contractors->links() }}</div>
        @endif
    </x-card>
</div>
