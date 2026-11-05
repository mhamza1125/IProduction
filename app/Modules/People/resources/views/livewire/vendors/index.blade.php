<div>
    <x-page-header title="Vendors" description="Suppliers of materials and goods. Vendor ledgers are in PKR.">
        @can('vendors.create')
            <x-button :href="route('people.vendors.create')" icon="plus">New vendor</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search code, name, contact, NTN…" dates :export="auth()->user()->can('vendors.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="">All</option>
            </select>
        </x-table.toolbar>

        <x-table empty="No vendors found.">
            <x-slot:head>
                <x-table.th sortable="code" :sort-field="$sortField" :sort-direction="$sortDirection">Code</x-table.th>
                <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Name</x-table.th>
                <x-table.th>Contact</x-table.th>
                <x-table.th>NTN</x-table.th>
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($vendors as $v)
                <tr wire:key="v-{{ $v->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <x-table.td class="font-mono"><a href="{{ route('people.vendors.show', $v) }}" wire:navigate class="text-brand-600 hover:underline dark:text-brand-500">{{ $v->code }}</a></x-table.td>
                    <x-table.td class="font-medium">{{ $v->name }}</x-table.td>
                    <x-table.td class="text-zinc-500">{{ collect([$v->contact_person, $v->phone])->filter()->implode(' · ') }}</x-table.td>
                    <x-table.td>{{ $v->ntn }}</x-table.td>
                    <x-table.td><x-status :status="$v->is_active ? 'active' : 'inactive'" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($vendors->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $vendors->links() }}</div>
        @endif
    </x-card>
</div>
