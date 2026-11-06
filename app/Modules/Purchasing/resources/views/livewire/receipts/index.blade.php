<div>
    <x-page-header title="Goods receipts" description="What actually arrived. Create a receipt from an open purchase order." />
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number, PO, vendor, delivery note…" dates :export="auth()->user()->can('goods_receipts.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All statuses</option>
                @foreach (\App\Support\Documents\DocumentStatus::LABELS as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No goods receipts.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th sortable="receipt_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>PO</x-table.th><x-table.th>Vendor</x-table.th><x-table.th>Delivery note</x-table.th><x-table.th align="right">Value</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($receipts as $r)
                <tr wire:key="r-{{ $r->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('purchasing.receipts.show', $r) }}" wire:navigate class="text-brand-600 hover:underline">{{ $r->number }}</a></x-table.td>
                    <x-table.td>@date($r->receipt_date)</x-table.td>
                    <x-table.td class="font-mono text-xs">{{ $r->order->number }}</x-table.td>
                    <x-table.td>{{ $r->vendor->name }}</x-table.td>
                    <x-table.td>{{ $r->vendor_delivery_note }}</x-table.td>
                    <x-table.td align="right">@can('cost.view') @money($r->total_value) @endcan</x-table.td>
                    <x-table.td><x-status :status="$r->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($receipts->hasPages()) <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $receipts->links() }}</div> @endif
    </x-card>
</div>
