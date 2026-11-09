<div>
    <x-page-header title="Purchase orders" description="A purchase order is only a commitment. Stock and the vendor balance change when goods are received.">
        @can('purchase_orders.create')
            <x-button :href="route('purchasing.orders.create')" icon="plus">New purchase order</x-button>
        @endcan
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number or vendor…" dates :export="auth()->user()->can('purchase_orders.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All statuses</option>
                @foreach ($statuses as $s) <option value="{{ $s }}">{{ \Illuminate\Support\Str::headline($s) }}</option> @endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No purchase orders.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th sortable="order_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Vendor</x-table.th><x-table.th>Expected</x-table.th>
                <x-table.th sortable="total" :sort-field="$sortField" :sort-direction="$sortDirection" align="right">Total (PKR)</x-table.th>
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($orders as $o)
                <tr wire:key="po-{{ $o->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('purchasing.orders.show', $o) }}" wire:navigate class="text-link hover:underline">{{ $o->number }}</a></x-table.td>
                    <x-table.td>@date($o->order_date)</x-table.td>
                    <x-table.td>{{ $o->vendor->name }}</x-table.td>
                    <x-table.td>@date($o->expected_date)</x-table.td>
                    <x-table.td align="right">@money($o->total)</x-table.td>
                    <x-table.td><x-status :status="$o->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($orders->hasPages()) <div class="border-t border-line px-4 py-3">{{ $orders->links() }}</div> @endif
    </x-card>
</div>
