<div>
    <x-page-header title="Routes" description="The ordered stages each made item goes through.">
        @can('routes.create')
            <x-button :href="route('catalog.routes.create')" icon="plus">New route</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search route or item…" />
        <x-table empty="No routes yet.">
            <x-slot:head>
                <x-table.th>Item</x-table.th>
                <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Route</x-table.th>
                <x-table.th>Stages</x-table.th>
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($routes as $r)
                <tr wire:key="r-{{ $r->id }}">
                    <x-table.td><a href="{{ route('catalog.items.show', $r->item_id) }}" wire:navigate class="text-link hover:underline">{{ $r->item->code }}</a> {{ $r->item->name }}</x-table.td>
                    <x-table.td class="font-medium"><a href="{{ route('catalog.routes.edit', $r) }}" wire:navigate class="hover:underline">{{ $r->name }}</a> @if ($r->is_default)<x-badge color="blue">Default</x-badge>@endif</x-table.td>
                    <x-table.td class="text-xs text-subtle">{{ $r->stages->map(fn ($s) => $s->stage->name.($s->is_optional ? ' (optional)' : ''))->implode(' → ') }}</x-table.td>
                    <x-table.td><x-status :status="$r->is_active ? 'active' : 'inactive'" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($routes->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $routes->links() }}</div>
        @endif
    </x-card>
</div>
