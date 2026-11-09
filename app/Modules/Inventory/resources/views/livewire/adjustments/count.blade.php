<div>
    <x-page-header title="Physical count" description="Enter what you counted. Leave a row empty to skip it. Differences become a draft stock adjustment." />

    <form wire:submit="createAdjustment" class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-4">
                <x-select label="Location" wire:model.live="location_id" :options="$locations" required />
                <x-select label="Item type" wire:model.live="type" :options="$types" placeholder="All" />
                <x-date-input label="Count date" wire:model="count_date" required />
                <x-select label="Reason" wire:model="reason_id" :options="$reasons" placeholder="Choose…" required />
            </div>
        </x-card>

        <x-card :padding="false">
            <x-table empty="No available stock at this location.">
                <x-slot:head>
                    <x-table.th>Item</x-table.th><x-table.th>Stage</x-table.th><x-table.th>Lot</x-table.th>
                    <x-table.th align="right">System</x-table.th><x-table.th align="right">Counted</x-table.th>
                </x-slot:head>
                @foreach ($rows as $row)
                    <tr wire:key="c-{{ $row->id }}">
                        <x-table.td>{{ $row->variant->fullLabel() }}</x-table.td>
                        <x-table.td>{{ $row->stage?->name ?? '—' }}</x-table.td>
                        <x-table.td class="font-mono text-xs">{{ $row->lot->lot_number }}</x-table.td>
                        <x-table.td align="right">@qty($row->quantity)</x-table.td>
                        <x-table.td align="right"><input type="number" step="any" min="0" wire:model="counted.{{ $row->id }}" aria-label="Counted" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></x-table.td>
                    </tr>
                @endforeach
            </x-table>
            <x-slot:footer><x-button type="submit">Create adjustment from differences</x-button></x-slot:footer>
        </x-card>
    </form>
</div>
