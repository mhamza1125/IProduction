<div>
    <x-page-header :title="'Lot '.$lot->lot_number" :description="$lot->variant->fullLabel()" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Source">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <x-people::detail label="Origin" :value="\App\Modules\Inventory\Models\Lot::ORIGINS[$lot->origin] ?? $lot->origin" />
                    <x-people::detail label="Received" :value="\App\Support\Format::date($lot->received_date)" />
                    <x-people::detail label="Source document" :value="\App\Support\Documents\Reference::label($lot->source) ?: null" />
                    <x-people::detail label="Vendor" :value="$lot->vendor?->name" />
                    <x-people::detail label="Contractor" :value="$lot->contractor?->name" />
                    @can('cost.view') <x-people::detail label="Unit cost" :value="\App\Support\Format::money($lot->unit_cost, 'PKR', 4)" /> @endcan
                    <x-people::detail label="Notes" :value="$lot->notes" class="sm:col-span-3" />
                </dl>
            </x-card>

            <x-card title="Movements" :padding="false">
                <x-table empty="No movements.">
                    <x-slot:head>
                        <x-table.th>Date</x-table.th><x-table.th>Document</x-table.th><x-table.th>Stage · status · location</x-table.th>
                        <x-table.th align="right">In</x-table.th><x-table.th align="right">Out</x-table.th><x-table.th>By</x-table.th>
                    </x-slot:head>
                    @foreach ($movements as $m)
                        <tr wire:key="m-{{ $m->id }}">
                            <x-table.td>@date($m->movement_date)</x-table.td>
                            <x-table.td class="font-mono text-xs">{{ \App\Modules\Inventory\Livewire\Movements\Index::documentLabel($m) }}</x-table.td>
                            <x-table.td class="text-xs text-subtle">{{ collect([$m->stage?->name, $statusLabels[$m->status] ?? $m->status, $m->location->name])->filter()->implode(' · ') }}</x-table.td>
                            <x-table.td align="right">{{ (float) $m->qty_in ? \App\Support\Format::qty($m->qty_in) : '' }}</x-table.td>
                            <x-table.td align="right">{{ (float) $m->qty_out ? \App\Support\Format::qty($m->qty_out) : '' }}</x-table.td>
                            <x-table.td class="text-xs">{{ $m->user?->name }}</x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Where it is now">
                <ul class="space-y-1 text-sm">
                    @forelse ($balances as $b)
                        <li class="flex justify-between"><span>{{ collect([$b->location->name, $b->stage?->name, $statusLabels[$b->status]])->filter()->implode(' · ') }}</span><span class="tabular">@qty($b->quantity) {{ $lot->variant->item->stockUnit->symbol }}</span></li>
                    @empty
                        <li class="text-subtle">Nothing left in stock.</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card title="Made from">
                <ul class="space-y-1 text-sm">
                    @forelse ($inputs as $link)
                        <li><a href="{{ route('inventory.lots.show', $link->source_lot_id) }}" wire:navigate class="font-mono text-link hover:underline">{{ $link->sourceLot->lot_number }}</a> {{ $link->sourceLot->variant->item->name }} @if ($link->quantity) · @qty($link->quantity) @endif</li>
                    @empty
                        <li class="text-subtle">—</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card title="Used to make">
                <ul class="space-y-1 text-sm">
                    @forelse ($outputs as $link)
                        <li><a href="{{ route('inventory.lots.show', $link->lot_id) }}" wire:navigate class="font-mono text-link hover:underline">{{ $link->lot->lot_number }}</a> {{ $link->lot->variant->item->name }} @if ($link->quantity) · @qty($link->quantity) @endif</li>
                    @empty
                        <li class="text-subtle">—</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
</div>
