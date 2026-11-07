<div>
    <x-page-header :title="$batch->typeLabel().' · '.$batch->number" :description="'Opening date '.\App\Support\Format::date($batch->batch_date).' · '.$batch->file_name.' · '.$batch->row_count.' rows'" />
    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($batch->error_count === 0 || ! $batch->isDraft())
            <x-document-actions :document="$batch" />
        @else
            <x-status :status="$batch->status" />
            <x-badge color="red">{{ $batch->error_count }} rows with errors: fix the data or the file, then re-check</x-badge>
            @can('opening.cancel') <x-document-actions :document="$batch" /> @endcan
        @endif
        @if ($batch->isDraft())
            @can('opening.create') <x-button variant="secondary" wire:click="revalidate">Re-check rows</x-button> @endcan
            @can('opening.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete this draft import?">Delete</x-button> @endcan
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Preview" :padding="false" class="xl:col-span-2">
            <div class="border-b border-zinc-200 px-4 py-2 dark:border-zinc-800"><x-checkbox wire:model.live="errorsOnly" label="Only rows with errors" /></div>
            <x-table empty="No rows.">
                <x-slot:head><x-table.th>Row</x-table.th>@foreach ($columns as $c => $hint)<x-table.th>{{ str_replace('_', ' ', $c) }}</x-table.th>@endforeach<x-table.th>Check</x-table.th></x-slot:head>
                @foreach ($rows as $r)
                    <tr wire:key="or-{{ $r->id }}" @class(['bg-red-50 dark:bg-red-500/10' => $r->errors])>
                        <x-table.td class="text-xs text-zinc-500">{{ $r->row_number }}</x-table.td>
                        @foreach ($columns as $c => $hint)<x-table.td class="text-xs">{{ $r->data[$c] ?? '' }}</x-table.td>@endforeach
                        <x-table.td class="text-xs">@if ($r->errors)<span class="text-red-700 dark:text-red-400">{{ implode(' ', $r->errors) }}</span>@else<span class="text-green-700">OK</span>@endif</x-table.td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <div class="space-y-6">
            <x-card title="Control totals" description="Totals from the old system, per currency, for the cutover report.">
                <form wire:submit="saveControls" class="grid gap-2">
                    @foreach ($controls as $cur => $amount)<x-input :label="$cur" type="number" step="any" wire:model="controls.{{ $cur }}" />@endforeach
                    <x-input label="Add currency (e.g. PKR, USD)" wire:model="newCurrency" maxlength="3" />
                    @error('controls') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @can('opening.create') <x-button type="submit" size="sm" variant="secondary">Save control totals</x-button> @endcan
                </form>
                @if ($batch->totals)
                    <dl class="mt-4 grid gap-1 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-800">
                        @foreach ($batch->totals as $cur => $total)
                            @php($control = $batch->control_totals[$cur] ?? null)
                            <div class="flex justify-between"><dt>Imported {{ $cur }}</dt><dd class="tabular">@money($total)</dd></div>
                            @if ($control !== null)<div @class(['flex justify-between', 'text-red-600 font-semibold' => \App\Support\Decimal::compare($control, $total) !== 0])><dt>Difference</dt><dd class="tabular">@money(\App\Support\Decimal::sub($total, $control))</dd></div>@endif
                        @endforeach
                    </dl>
                @endif
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$batch" :key="'n-'.$batch->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$batch" :key="'h-'.$batch->id" /></x-card>
        </div>
    </div>
</div>
