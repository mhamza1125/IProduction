<div>
    <x-page-header title="Rejections" description="Rejected pieces stay in Rejected status at their stage until a decision is posted: rework, scrap, sell or recover a charge." />
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number, SKU, reason…" dates :export="auth()->user()->can('rejections.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All</option><option value="open">Open</option><option value="decided">Decided</option>
            </select>
        </x-table.toolbar>
        <x-table empty="No rejections.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th sortable="rejection_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Item · stage</x-table.th><x-table.th>Source</x-table.th>
                <x-table.th sortable="quantity" :sort-field="$sortField" :sort-direction="$sortDirection" align="right">Qty</x-table.th>
                <x-table.th align="right">Decided</x-table.th><x-table.th>Reason</x-table.th><x-table.th>Responsible</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($rejections as $r)
                <tr wire:key="rj-{{ $r->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('production.rejections.show', $r) }}" wire:navigate class="text-brand-600 hover:underline">{{ $r->number }}</a></x-table.td>
                    <x-table.td>@date($r->rejection_date)</x-table.td>
                    <x-table.td>{{ $r->variant->sku }}@if ($r->stage) · {{ $r->stage->name }}@endif</x-table.td>
                    <x-table.td class="font-mono text-xs">{{ $r->ptc?->number ?? $r->job?->number ?? 'Store' }}</x-table.td>
                    <x-table.td align="right">@qty($r->quantity)</x-table.td><x-table.td align="right">@qty($r->decided_qty)</x-table.td>
                    <x-table.td class="text-xs">{{ $r->reason }}</x-table.td><x-table.td>{{ $r->responsible?->name }}</x-table.td>
                    <x-table.td><x-status :status="$r->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($rejections->hasPages()) <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $rejections->links() }}</div> @endif
    </x-card>
</div>
