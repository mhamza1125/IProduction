<div>
    <x-page-header :title="'Journal '.$journal->number" :description="\App\Support\Format::date($journal->journal_date).' · '.$journal->currency_code.' · '.$journal->description">
        @if ($journal->source && method_exists($journal->source, 'showUrl'))
            <x-button variant="secondary" :href="$journal->source->showUrl()">Open {{ $journal->source->number }}</x-button>
        @endif
    </x-page-header>
    <x-card :padding="false" class="max-w-4xl">
        <x-table>
            <x-slot:head><x-table.th>Account</x-table.th><x-table.th>Party / bank</x-table.th><x-table.th>Memo</x-table.th><x-table.th align="right">Debit</x-table.th><x-table.th align="right">Credit</x-table.th></x-slot:head>
            @foreach ($lines as $l)
                <tr>
                    <x-table.td><span class="font-mono text-xs">{{ $l->account->code }}</span> {{ $l->account->name }}</x-table.td>
                    <x-table.td>{{ $l->ledgerable?->name ?? $l->moneyAccount?->name }}</x-table.td>
                    <x-table.td class="text-zinc-500">{{ $l->memo }}</x-table.td>
                    <x-table.td align="right">{{ (float) $l->debit ? \App\Support\Format::money($l->debit) : '' }}</x-table.td>
                    <x-table.td align="right">{{ (float) $l->credit ? \App\Support\Format::money($l->credit) : '' }}</x-table.td>
                </tr>
            @endforeach
            <tr class="font-semibold"><x-table.td colspan="3">Total</x-table.td><x-table.td align="right">@money(\App\Support\Decimal::sum($lines->pluck('debit')))</x-table.td><x-table.td align="right">@money(\App\Support\Decimal::sum($lines->pluck('credit')))</x-table.td></tr>
        </x-table>
    </x-card>
</div>
