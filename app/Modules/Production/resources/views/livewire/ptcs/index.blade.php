<div>
    <x-page-header title="PTCs" description="Product Travel Cards: every production job, from material issue to release.">
        @can('ptcs.create') <x-button :href="route('production.ptcs.create')" icon="plus">New PTC</x-button> @endcan
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number, SKU, order…" dates :export="auth()->user()->can('ptcs.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All statuses</option>@foreach ($statuses as $s)<option value="{{ $s }}">{{ \Illuminate\Support\Str::headline($s) }}</option>@endforeach
            </select>
            <x-checkbox wire:model.live="late" label="Late only" />
        </x-table.toolbar>
        <x-table empty="No PTCs.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th sortable="start_date" :sort-field="$sortField" :sort-direction="$sortDirection">Start</x-table.th>
                <x-table.th>Item</x-table.th><x-table.th>Order</x-table.th>
                <x-table.th sortable="planned_qty" :sort-field="$sortField" :sort-direction="$sortDirection" align="right">Planned</x-table.th>
                <x-table.th align="right">Released</x-table.th>
                <x-table.th sortable="target_date" :sort-field="$sortField" :sort-direction="$sortDirection">Target</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($ptcs as $p)
                <tr wire:key="ptc-{{ $p->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('production.ptcs.show', $p) }}" wire:navigate class="text-link hover:underline">{{ $p->number }}</a>@if ($p->parent)<div class="text-xs text-subtle">child of {{ $p->parent->number }}</div>@endif</x-table.td>
                    <x-table.td>@date($p->start_date)</x-table.td>
                    <x-table.td>{{ $p->variant->fullLabel() }}</x-table.td>
                    <x-table.td>@if ($p->orderLine)<span class="font-mono text-xs">{{ $p->orderLine->order->number }}</span> · {{ $p->orderLine->order->customer->name }}@else <span class="text-subtle">Stock</span> @endif</x-table.td>
                    <x-table.td align="right">@qty($p->planned_qty)</x-table.td>
                    <x-table.td align="right">@qty($p->released_qty)</x-table.td>
                    <x-table.td @class(['text-danger font-medium' => ! $p->isFinished() && $p->target_date?->isPast()])>@date($p->target_date)</x-table.td>
                    <x-table.td><x-status :status="$p->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($ptcs->hasPages()) <div class="border-t border-line px-4 py-3">{{ $ptcs->links() }}</div> @endif
    </x-card>
</div>
