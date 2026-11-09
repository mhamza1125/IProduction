<div>
    <x-page-header title="Sales orders" description="The customer's order is the single source of ordered quantity and price, in the customer's currency.">
        @can('sales_orders.create') <x-button :href="route('sales.orders.create')" icon="plus">New sales order</x-button> @endcan
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number, customer, customer PO…" dates :export="auth()->user()->can('sales_orders.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All statuses</option>@foreach ($statuses as $s)<option value="{{ $s }}">{{ \Illuminate\Support\Str::headline($s) }}</option>@endforeach
            </select>
            <x-checkbox wire:model.live="late" label="Late only" />
        </x-table.toolbar>
        <x-table empty="No sales orders.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th sortable="order_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Customer</x-table.th><x-table.th>Customer PO</x-table.th>
                <x-table.th sortable="due_date" :sort-field="$sortField" :sort-direction="$sortDirection">Due</x-table.th>
                <x-table.th sortable="total" :sort-field="$sortField" :sort-direction="$sortDirection" align="right">Total</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($orders as $o)
                <tr wire:key="so-{{ $o->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('sales.orders.show', $o) }}" wire:navigate class="text-link hover:underline">{{ $o->number }}</a></x-table.td>
                    <x-table.td>@date($o->order_date)</x-table.td>
                    <x-table.td>{{ $o->customer->name }}</x-table.td>
                    <x-table.td>{{ $o->customer_po_number }}</x-table.td>
                    <x-table.td @class(['text-danger font-medium' => $o->isOpen() && $o->due_date?->isPast()])>@date($o->due_date)</x-table.td>
                    <x-table.td align="right">@money($o->total, $o->currency_code)</x-table.td>
                    <x-table.td><x-status :status="$o->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($orders->hasPages()) <div class="border-t border-line px-4 py-3">{{ $orders->links() }}</div> @endif
    </x-card>
</div>
