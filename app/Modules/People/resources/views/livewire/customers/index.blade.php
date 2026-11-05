<div>
    <x-page-header title="Customers" description="Each customer has one currency; their ledger is kept in that currency.">
        @can('customers.create')
            <x-button :href="route('people.customers.create')" icon="plus">New customer</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search code, name, company, email…" dates :export="auth()->user()->can('customers.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="">All</option>
            </select>
            <select wire:model.live="currency" aria-label="Currency" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All currencies</option>
                @foreach ($currencies as $code)
                    <option value="{{ $code }}">{{ $code }}</option>
                @endforeach
            </select>
        </x-table.toolbar>

        <x-table empty="No customers found.">
            <x-slot:head>
                <x-table.th sortable="code" :sort-field="$sortField" :sort-direction="$sortDirection">Code</x-table.th>
                <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Name</x-table.th>
                <x-table.th sortable="currency_code" :sort-field="$sortField" :sort-direction="$sortDirection">Currency</x-table.th>
                <x-table.th sortable="country" :sort-field="$sortField" :sort-direction="$sortDirection">Country</x-table.th>
                <x-table.th>Contact</x-table.th>
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($customers as $c)
                <tr wire:key="c-{{ $c->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <x-table.td class="font-mono"><a href="{{ route('people.customers.show', $c) }}" wire:navigate class="text-brand-600 hover:underline dark:text-brand-500">{{ $c->code }}</a></x-table.td>
                    <x-table.td class="font-medium">{{ $c->name }} @if ($c->company)<span class="text-zinc-500">· {{ $c->company }}</span>@endif</x-table.td>
                    <x-table.td class="font-mono">{{ $c->currency_code }}</x-table.td>
                    <x-table.td>{{ $c->country }}</x-table.td>
                    <x-table.td class="text-zinc-500">{{ collect([$c->email, $c->phone])->filter()->implode(' · ') }}</x-table.td>
                    <x-table.td><x-status :status="$c->is_active ? 'active' : 'inactive'" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($customers->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $customers->links() }}</div>
        @endif
    </x-card>
</div>
