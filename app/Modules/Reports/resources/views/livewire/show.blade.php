<div>
    <x-page-header :title="$report->title()" :description="$report->description()">
        <a href="{{ route('reports.index') }}" wire:navigate class="text-sm text-zinc-500 hover:underline">All reports</a>
    </x-page-header>
    <x-card :padding="false">
        <div class="flex flex-wrap items-end gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            @foreach ($filterDefs as $f)
                @if ($f->type === 'date')
                    <x-date-input :label="$f->label" wire:model.live="filters.{{ $f->key }}" :live="true" />
                @elseif ($f->type === 'text')
                    <x-input :label="$f->label" wire:model.live.debounce.400ms="filters.{{ $f->key }}" />
                @else
                    <x-select :label="$f->label" wire:model.live="filters.{{ $f->key }}" :options="$f->options" placeholder="All" />
                @endif
            @endforeach
            <div class="ml-auto flex gap-2">
                @can('reports.print') <a href="{{ route('reports.print', ['key' => $report->key(), 'f' => $filters]) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"><x-icon name="printer" class="size-4" /> Print</a> @endcan
                @can('reports.export')
                    <x-button size="sm" variant="secondary" wire:click="export('xlsx')">Excel</x-button>
                    <x-button size="sm" variant="ghost" wire:click="export('csv')">CSV</x-button>
                @endcan
            </div>
        </div>
        <x-table empty="Nothing to report for these filters.">
            <x-slot:head>
                @foreach ($columns as $c)<x-table.th :align="$c->isNumeric() ? 'right' : 'left'">{{ $c->label }}</x-table.th>@endforeach
            </x-slot:head>
            @foreach ($rows as $i => $row)
                <tr wire:key="rr-{{ $rows->currentPage() }}-{{ $i }}">
                    @foreach ($columns as $c)
                        <x-table.td :align="$c->isNumeric() ? 'right' : 'left'" @class(['font-mono text-xs' => $c->linkKey !== null])>
                            @if ($c->linkKey && ! empty($row[$c->linkKey]))<a href="{{ $row[$c->linkKey] }}" wire:navigate class="text-brand-600 hover:underline">{{ \App\Modules\Reports\Support\ReportFormatter::display($c, $row) }}</a>@else{{ \App\Modules\Reports\Support\ReportFormatter::display($c, $row) }}@endif
                        </x-table.td>
                    @endforeach
                </tr>
            @endforeach
            @if ($totals !== [])
                @foreach (collect($totals)->flatMap(fn ($byCur) => array_keys($byCur))->unique() as $currency)
                    <tr class="font-semibold">
                        @foreach ($columns as $j => $c)
                            <x-table.td :align="$c->isNumeric() ? 'right' : 'left'">@if ($j === 0)Total {{ $currency }}@elseif (isset($totals[$c->key][$currency])){{ $c->type === 'qty' ? \App\Support\Format::qty($totals[$c->key][$currency]) : \App\Support\Format::money($totals[$c->key][$currency]) }}@endif</x-table.td>
                        @endforeach
                    </tr>
                @endforeach
            @endif
        </x-table>
        @if ($rows->hasPages()) <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $rows->links() }}</div> @endif
        @if ($report->note()) <p class="border-t border-zinc-200 px-4 py-3 text-xs text-zinc-500 dark:border-zinc-800">{{ $report->note() }}</p> @endif
    </x-card>
</div>
