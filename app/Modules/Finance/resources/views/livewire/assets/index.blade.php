<div>
    <x-page-header title="Asset register" description="A record of company assets (bikes, ACs, computers, land). It does not post to accounts or stock.">
        @can('assets.create') <x-button :href="route('finance.assets.create')" icon="plus">New asset</x-button> @endcan
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search code, name, category, serial, location…" dates :export="auth()->user()->can('assets.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All statuses</option>@foreach (\App\Modules\Finance\Models\Asset::STATUSES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No assets recorded.">
            <x-slot:head>
                <x-table.th sortable="code" :sort-field="$sortField" :sort-direction="$sortDirection">Code</x-table.th>
                <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Name</x-table.th>
                <x-table.th>Category</x-table.th>
                <x-table.th sortable="purchase_date" :sort-field="$sortField" :sort-direction="$sortDirection">Purchased</x-table.th>
                <x-table.th sortable="value" :sort-field="$sortField" :sort-direction="$sortDirection" align="right">Value</x-table.th>
                <x-table.th>Location · assigned</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($assets as $a)
                <tr wire:key="as-{{ $a->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('finance.assets.edit', $a) }}" wire:navigate class="text-link hover:underline">{{ $a->code }}</a></x-table.td>
                    <x-table.td>{{ $a->name }}@if ($a->serial_number)<div class="text-xs text-subtle">S/N {{ $a->serial_number }}</div>@endif</x-table.td>
                    <x-table.td>{{ $a->category }}</x-table.td>
                    <x-table.td>@date($a->purchase_date)</x-table.td>
                    <x-table.td align="right">@if ($a->value !== null)@money($a->value)@endif</x-table.td>
                    <x-table.td>{{ $a->location }}@if ($a->assignedTo)<div class="text-xs text-subtle">{{ $a->assignedTo->name }}</div>@endif</x-table.td>
                    <x-table.td><x-status :status="$a->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($assets->hasPages()) <div class="border-t border-line px-4 py-3">{{ $assets->links() }}</div> @endif
    </x-card>
</div>
