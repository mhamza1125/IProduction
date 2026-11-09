<div>
    <x-page-header title="Ledgers" description="Built from posted journal lines only. Rows open their source document.">
        @if ($subject)
            @can('ledgers.export')
                <x-button variant="secondary" wire:click="export('xlsx')">Excel</x-button>
                <x-button variant="secondary" wire:click="export('csv')">CSV</x-button>
            @endcan
            @can('ledgers.print')
                <a href="{{ route('finance.ledgers.print', ['type' => $type, 'id' => $id, 'from' => $dateFrom, 'to' => $dateTo, 'currency' => $currency]) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-md bg-raised px-3.5 py-2 text-sm font-medium shadow-sm ring-1 ring-line-strong hover:bg-hover"><x-icon name="printer" class="size-4" /> Print</a>
            @endcan
        @endif
    </x-page-header>

    <x-card class="mb-6">
        <div class="grid items-end gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-select label="Ledger of" wire:model.live="type" :options="$types" />
            <x-select label="Record" wire:model.live="id" :options="$options" placeholder="Choose…" class="lg:col-span-2" />
            <x-date-input label="From" wire:model="dateFrom" :live="true" />
            <x-date-input label="To" wire:model="dateTo" :live="true" />
            @if ($type === 'gl')
                <x-select label="Currency" wire:model.live="currency" :options="$currencies" />
            @endif
        </div>
    </x-card>

    @if ($subject && $ledger)
        <x-card :padding="false" :title="($subject->code ?? '').' '.$subject->name" :description="'Amounts in '.$ledgerCurrency.'. Balance = debit − credit.'">
            <x-table>
                <x-slot:head>
                    <x-table.th>Date</x-table.th><x-table.th>Document</x-table.th><x-table.th>Description</x-table.th>
                    <x-table.th align="right">Debit</x-table.th><x-table.th align="right">Credit</x-table.th><x-table.th align="right">Balance</x-table.th>
                </x-slot:head>
                <tr class="bg-sunken"><x-table.td colspan="5" class="font-medium">Opening balance</x-table.td><x-table.td align="right" class="font-medium">@money($ledger['opening'])</x-table.td></tr>
                @foreach ($ledger['rows'] as $row)
                    @php($line = $row['line'])
                    @php($source = $line->journal->source)
                    <tr wire:key="jl-{{ $line->id }}">
                        <x-table.td class="whitespace-nowrap">@date($line->line_date)</x-table.td>
                        <x-table.td class="whitespace-nowrap font-mono text-xs">
                            @if ($source && method_exists($source, 'showUrl'))
                                <a href="{{ $source->showUrl() }}" wire:navigate class="text-link hover:underline">{{ $source->number }}</a>
                            @else
                                <a href="{{ route('finance.journals.show', $line->journal_id) }}" wire:navigate class="text-link hover:underline">{{ $source?->number ?? $line->journal->number }}</a>
                            @endif
                        </x-table.td>
                        <x-table.td>{{ $line->memo ?? $line->journal->description }} @if ($type !== 'gl')<span class="text-xs text-subtle">· {{ $line->account->name }}</span>@endif</x-table.td>
                        <x-table.td align="right">{{ (float) $line->debit ? \App\Support\Format::money($line->debit) : '' }}</x-table.td>
                        <x-table.td align="right">{{ (float) $line->credit ? \App\Support\Format::money($line->credit) : '' }}</x-table.td>
                        <x-table.td align="right">@money($row['balance'])</x-table.td>
                    </tr>
                @endforeach
                <tr class="bg-sunken font-semibold">
                    <x-table.td colspan="3">Closing balance</x-table.td>
                    <x-table.td align="right">@money($ledger['debits'])</x-table.td><x-table.td align="right">@money($ledger['credits'])</x-table.td>
                    <x-table.td align="right">@money($ledger['closing'], $ledgerCurrency)</x-table.td>
                </tr>
            </x-table>
        </x-card>
    @else
        <p class="text-sm text-subtle">Choose a record to see its ledger.</p>
    @endif
</div>
