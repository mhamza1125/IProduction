<div>
    <x-page-header title="Vendor bills" description="What vendors invoice for accepted receipts. Payments are allocated to bills.">
        @can('vendor_bills.create') <x-button :href="route('purchasing.bills.create')" icon="plus">New bill</x-button> @endcan
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number, vendor invoice, vendor…" dates :export="auth()->user()->can('vendor_bills.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All statuses</option>
                @foreach (['draft', 'approved', 'posted', 'partly_paid', 'paid', 'cancelled'] as $s) <option value="{{ $s }}">{{ \Illuminate\Support\Str::headline($s) }}</option> @endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No bills.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th>Vendor invoice</x-table.th><x-table.th>Vendor</x-table.th>
                <x-table.th sortable="bill_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th sortable="due_date" :sort-field="$sortField" :sort-direction="$sortDirection">Due</x-table.th>
                <x-table.th align="right">Total</x-table.th><x-table.th align="right">Balance</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($bills as $b)
                <tr wire:key="b-{{ $b->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('purchasing.bills.show', $b) }}" wire:navigate class="text-brand-600 hover:underline">{{ $b->number }}</a></x-table.td>
                    <x-table.td>{{ $b->vendor_invoice_number }}</x-table.td>
                    <x-table.td>{{ $b->vendor->name }}</x-table.td>
                    <x-table.td>@date($b->bill_date)</x-table.td>
                    <x-table.td @class(['text-red-600' => $b->due_date && $b->due_date->isPast() && (float) $b->balance() > 0])>@date($b->due_date)</x-table.td>
                    <x-table.td align="right">@money($b->total)</x-table.td>
                    <x-table.td align="right">@money($b->balance())</x-table.td>
                    <x-table.td><x-status :status="$b->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($bills->hasPages()) <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $bills->links() }}</div> @endif
    </x-card>
</div>
