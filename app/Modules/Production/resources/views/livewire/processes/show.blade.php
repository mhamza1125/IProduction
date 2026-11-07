<div>
    <x-page-header :title="'Material processing '.$process->number" :description="$process->process_name.' · '.\App\Support\Format::date($process->process_date)" />
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <x-document-actions :document="$process" :edit-route="route('production.processes.edit', $process)" />
        @if ($process->isDraft()) @can('material_processes.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan @endif
    </div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @foreach (['input' => 'Inputs', 'output' => 'Outputs', 'loss' => 'Losses'] as $kind => $title)
                @php($rows = $process->lines->where('kind', $kind))
                @if ($rows->isNotEmpty())
                    <x-card :title="$title" :padding="false">
                        <x-table>
                            <x-slot:head><x-table.th>{{ $kind === 'loss' ? 'Type' : 'Material' }}</x-table.th>@if ($kind === 'output')<x-table.th align="right">Expected</x-table.th>@endif<x-table.th align="right">Quantity</x-table.th><x-table.th>Lot</x-table.th>@can('cost.view')@if ($kind !== 'loss')<x-table.th align="right">Cost</x-table.th>@endif @endcan<x-table.th>{{ $kind === 'loss' ? 'Reason' : 'Location' }}</x-table.th></x-slot:head>
                            @foreach ($rows as $l)
                                <tr wire:key="pl-{{ $l->id }}">
                                    <x-table.td>{{ $kind === 'loss' ? ucfirst((string) $l->loss_type) : $l->variant?->fullLabel() }}</x-table.td>
                                    @if ($kind === 'output')<x-table.td align="right">@qty($l->expected_qty)</x-table.td>@endif
                                    <x-table.td align="right">@qty($l->quantity)</x-table.td>
                                    <x-table.td>@if ($l->lot)<a href="{{ route('inventory.lots.show', $l->lot) }}" wire:navigate class="font-mono text-xs text-brand-600 hover:underline">{{ $l->lot->lot_number }}</a>@endif</x-table.td>
                                    @can('cost.view')@if ($kind !== 'loss')<x-table.td align="right">@if ($l->total_cost !== null)@money($l->total_cost)@endif</x-table.td>@endif @endcan
                                    <x-table.td>{{ $kind === 'loss' ? $l->reason : $l->location?->name }}</x-table.td>
                                </tr>
                            @endforeach
                        </x-table>
                    </x-card>
                @endif
            @endforeach
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$process" :key="'a-'.$process->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Yield">
                <dl class="grid gap-2 text-sm">
                    <div class="flex justify-between"><dt>Input quantity</dt><dd class="tabular">@qty($yield['input'])</dd></div>
                    <div class="flex justify-between"><dt>Expected output</dt><dd class="tabular">@qty($yield['expected'])</dd></div>
                    <div class="flex justify-between"><dt>Actual output</dt><dd class="tabular font-semibold">@qty($yield['actual'])</dd></div>
                    <div class="flex justify-between"><dt>Wastage</dt><dd class="tabular">{{ $yield['wastage_percent'] !== null ? $yield['wastage_percent'].'%' : '—' }}</dd></div>
                    @can('cost.view')
                        <div class="flex justify-between border-t border-zinc-200 pt-2 dark:border-zinc-800"><dt>Input cost</dt><dd class="tabular">@money($process->input_cost)</dd></div>
                        <div class="flex justify-between"><dt>Labor</dt><dd class="tabular">@money($process->labor_cost)</dd></div>
                        <div class="flex justify-between"><dt>Recoverable value</dt><dd class="tabular">@money($process->recoverable_value)</dd></div>
                        <div class="flex justify-between font-semibold"><dt>Cost per output unit</dt><dd class="tabular">@money($process->output_unit_cost, null, 4)</dd></div>
                    @endcan
                </dl>
            </x-card>
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Worker" :value="$process->employee?->name ?? $process->contractor?->name" />
                    <x-people::detail label="Notes" :value="$process->notes" />
                </dl>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$process" :key="'n-'.$process->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$process" :key="'h-'.$process->id" /></x-card>
        </div>
    </div>
</div>
