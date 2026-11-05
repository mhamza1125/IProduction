<div>
    <x-page-header title="Stock" description="All quantities come from posted documents. Free stock = Available − reserved." />

    <nav class="mb-4 flex gap-1 border-b border-zinc-200 dark:border-zinc-800" aria-label="Stock views">
        @foreach (['summary' => 'Summary', 'stages' => 'By stage', 'locations' => 'By location and lot'] as $key => $label)
            <button type="button" wire:click="$set('view', '{{ $key }}')"
                    @class(['border-b-2 px-3 py-2 text-sm font-medium', 'border-brand-600 text-brand-700 dark:text-brand-500' => $view === $key, 'border-transparent text-zinc-500 hover:text-zinc-800' => $view !== $key])>{{ $label }}</button>
        @endforeach
    </nav>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search SKU, item{{ $view === 'locations' ? ', lot' : '' }}…" :export="auth()->user()->can('stock.export')">
            <select wire:model.live="type" aria-label="Item type" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All item types</option>
                @foreach ($types as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            <select wire:model.live="location" aria-label="Location" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All locations</option>
                @foreach ($locations as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
            </select>
            <x-checkbox wire:model.live="includeZero" label="Show zero" />
        </x-table.toolbar>

        @if ($view === 'summary')
            <x-table empty="No stock.">
                <x-slot:head>
                    <x-table.th sortable="sku" :sort-field="$sortField" :sort-direction="$sortDirection">SKU</x-table.th>
                    <x-table.th>Item</x-table.th>
                    <x-table.th align="right">Idle</x-table.th>
                    <x-table.th align="right">In production</x-table.th>
                    <x-table.th align="right">Rejected</x-table.th>
                    <x-table.th align="right">Returned</x-table.th>
                    <x-table.th align="right">Reserved</x-table.th>
                    <x-table.th align="right">Free</x-table.th>
                    <x-table.th>Unit</x-table.th>
                </x-slot:head>
                @foreach ($variants as $v)
                    @php($q = $quantities[$v->id])
                    <tr wire:key="s-{{ $v->id }}">
                        <x-table.td class="font-mono"><a href="{{ route('inventory.movements', ['q' => $v->sku]) }}" wire:navigate class="text-brand-600 hover:underline">{{ $v->sku }}</a></x-table.td>
                        <x-table.td>{{ $v->item->name }} @if ($v->label !== '')<span class="text-zinc-500">· {{ $v->label }}</span>@endif</x-table.td>
                        <x-table.td align="right">@qty($q['available'])</x-table.td>
                        <x-table.td align="right">@qty($q['in_production'])</x-table.td>
                        <x-table.td align="right" @class(['text-red-600' => (float) $q['rejected'] > 0])>@qty($q['rejected'])</x-table.td>
                        <x-table.td align="right">@qty($q['returned'])</x-table.td>
                        <x-table.td align="right">@qty($q['reserved'])</x-table.td>
                        <x-table.td align="right" class="font-semibold">@qty($q['free'])</x-table.td>
                        <x-table.td>{{ $v->item->stockUnit->symbol }}</x-table.td>
                    </tr>
                @endforeach
            </x-table>
        @elseif ($view === 'stages')
            <x-table empty="No stock.">
                <x-slot:head>
                    <x-table.th sortable="sku" :sort-field="$sortField" :sort-direction="$sortDirection">SKU</x-table.th>
                    <x-table.th>Item</x-table.th>
                    <x-table.th align="right">Store (no stage)</x-table.th>
                    @foreach ($stages as $stage) <x-table.th align="right">{{ $stage->name }}</x-table.th> @endforeach
                </x-slot:head>
                @foreach ($variants as $v)
                    <tr wire:key="st-{{ $v->id }}">
                        <x-table.td class="font-mono">{{ $v->sku }}</x-table.td>
                        <x-table.td>{{ $v->item->name }} @if ($v->label !== '')<span class="text-zinc-500">· {{ $v->label }}</span>@endif</x-table.td>
                        <x-table.td align="right">@qty($quantities[$v->id][0] ?? '0')</x-table.td>
                        @foreach ($stages as $stage) <x-table.td align="right">@qty($quantities[$v->id][$stage->id] ?? '0')</x-table.td> @endforeach
                    </tr>
                @endforeach
            </x-table>
        @else
            <x-table empty="No stock.">
                <x-slot:head>
                    <x-table.th>Location</x-table.th>
                    <x-table.th>SKU</x-table.th>
                    <x-table.th>Item</x-table.th>
                    <x-table.th>Lot</x-table.th>
                    <x-table.th>Stage</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <x-table.th align="right">Quantity</x-table.th>
                    @can('cost.view') <x-table.th align="right">Unit cost</x-table.th> @endcan
                </x-slot:head>
                @foreach ($buckets as $b)
                    <tr wire:key="b-{{ $b->id }}">
                        <x-table.td>{{ $b->location->name }} @if ($b->location->type === 'contractor')<x-badge color="amber">contractor</x-badge>@endif</x-table.td>
                        <x-table.td class="font-mono">{{ $b->variant->sku }}</x-table.td>
                        <x-table.td>{{ $b->variant->item->name }} @if ($b->variant->label !== '')<span class="text-zinc-500">· {{ $b->variant->label }}</span>@endif</x-table.td>
                        <x-table.td class="font-mono"><a href="{{ route('inventory.lots.show', $b->lot_id) }}" wire:navigate class="text-brand-600 hover:underline">{{ $b->lot->lot_number }}</a></x-table.td>
                        <x-table.td>{{ $b->stage?->name ?? '—' }}</x-table.td>
                        <x-table.td><x-badge :color="$b->status === 'available' ? 'green' : ($b->status === 'rejected' ? 'red' : 'amber')">{{ $statusLabels[$b->status] }}</x-badge></x-table.td>
                        <x-table.td align="right">@qty($b->quantity) {{ $b->variant->item->stockUnit->symbol }}</x-table.td>
                        @can('cost.view') <x-table.td align="right">@money($b->lot->unit_cost, null, 4)</x-table.td> @endcan
                    </tr>
                @endforeach
            </x-table>
        @endif

        @php($pager = $view === 'locations' ? $buckets : $variants)
        @if ($pager->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $pager->links() }}</div>
        @endif
    </x-card>
</div>
