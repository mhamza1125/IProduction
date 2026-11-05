<div>
    <x-page-header title="BOMs" description="Bills of materials: the components needed for one unit. Create versions from the item page." />

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search item…">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="current">Active and drafts</option>
                <option value="draft">Drafts</option>
                <option value="approved">Approved</option>
                <option value="superseded">Superseded</option>
                <option value="">All</option>
            </select>
        </x-table.toolbar>
        <x-table empty="No BOMs.">
            <x-slot:head>
                <x-table.th>Item</x-table.th>
                <x-table.th sortable="version" :sort-field="$sortField" :sort-direction="$sortDirection">Version</x-table.th>
                <x-table.th sortable="effective_date" :sort-field="$sortField" :sort-direction="$sortDirection">Effective</x-table.th>
                <x-table.th align="right">Components</x-table.th>
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($boms as $b)
                <tr wire:key="b-{{ $b->id }}">
                    <x-table.td>{{ $b->item->code }} · {{ $b->item->name }}</x-table.td>
                    <x-table.td><a href="{{ route('catalog.boms.edit', $b) }}" wire:navigate class="font-medium text-brand-600 hover:underline">v{{ $b->version }}</a></x-table.td>
                    <x-table.td>@date($b->effective_date)</x-table.td>
                    <x-table.td align="right">{{ $b->lines_count }}</x-table.td>
                    <x-table.td><x-status :status="$b->is_active ? 'active' : $b->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($boms->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $boms->links() }}</div>
        @endif
    </x-card>
</div>
