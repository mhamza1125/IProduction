<div>
    <x-page-header title="Journals" description="Balanced double-entry postings made automatically by documents. Journals are never edited; corrections post a reversal." />
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number or description…" dates />
        <x-table empty="No journals yet.">
            <x-slot:head>
                <x-table.th sortable="journal_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Journal</x-table.th>
                <x-table.th>Description</x-table.th><x-table.th>Currency</x-table.th><x-table.th align="right">Amount</x-table.th>
            </x-slot:head>
            @foreach ($journals as $j)
                <tr wire:key="j-{{ $j->id }}">
                    <x-table.td>@date($j->journal_date)</x-table.td>
                    <x-table.td class="font-mono"><a href="{{ route('finance.journals.show', $j) }}" wire:navigate class="text-link hover:underline">{{ $j->number }}</a> @if ($j->reverses_journal_id)<x-badge color="amber">reversal</x-badge>@endif</x-table.td>
                    <x-table.td>{{ $j->description }}</x-table.td>
                    <x-table.td class="font-mono">{{ $j->currency_code }}</x-table.td>
                    <x-table.td align="right">@money($j->lines_sum_debit)</x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($journals->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $journals->links() }}</div>
        @endif
    </x-card>
</div>
