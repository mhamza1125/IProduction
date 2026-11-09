<div>
    <x-page-header title="Purchase returns" description="Goods sent back to vendors. Create a return from a posted goods receipt.">
        
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number, reason, vendor…" dates :export="auth()->user()->can('purchase_returns.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All statuses</option>
                @foreach (['draft', 'approved', 'posted', 'cancelled'] as $s) <option value="{{ $s }}">{{ \Illuminate\Support\Str::headline($s) }}</option> @endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No returns.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th>Vendor</x-table.th><x-table.th sortable="return_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Reason</x-table.th><x-table.th align="right">Total</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($returns as $r)
                <tr wire:key="r-{{ $r->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('purchasing.returns.show', $r) }}" wire:navigate class="text-link hover:underline">{{ $r->number }}</a></x-table.td>
                    <x-table.td>{{ $r->vendor->name }}</x-table.td><x-table.td>@date($r->return_date)</x-table.td><x-table.td>{{ $r->reason }}</x-table.td>
                    <x-table.td align="right">@money($r->total)</x-table.td><x-table.td><x-status :status="$r->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($returns->hasPages()) <div class="border-t border-line px-4 py-3">{{ $returns->links() }}</div> @endif
    </x-card>
</div>
