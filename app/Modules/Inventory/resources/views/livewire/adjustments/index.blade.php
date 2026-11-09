<div>
    <x-page-header title="Stock adjustments" description="Corrections to stock: Draft → Approved → Posted. Physical counts can be turned into an adjustment.">
        @can('stock_adjustments.create')
            <x-button variant="secondary" :href="route('inventory.adjustments.count')">Physical count</x-button>
            <x-button :href="route('inventory.adjustments.create')" icon="plus">New adjustment</x-button>
        @endcan
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number or notes…" dates :export="auth()->user()->can('stock_adjustments.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All statuses</option>
                @foreach ($statuses as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No adjustments.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th sortable="adjustment_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Reason</x-table.th>
                <x-table.th align="right">Lines</x-table.th>
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($adjustments as $a)
                <tr wire:key="a-{{ $a->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('inventory.adjustments.show', $a) }}" wire:navigate class="text-link hover:underline">{{ $a->number }}</a> @if ($a->from_count)<x-badge>count</x-badge>@endif</x-table.td>
                    <x-table.td>@date($a->adjustment_date)</x-table.td>
                    <x-table.td>{{ $a->reason?->name }}</x-table.td>
                    <x-table.td align="right">{{ $a->lines_count }}</x-table.td>
                    <x-table.td><x-status :status="$a->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($adjustments->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $adjustments->links() }}</div>
        @endif
    </x-card>
</div>
