<div>
    <x-page-header title="Units" description="Units of measure and conversions between them. Item-specific conversions are set on the item.">
        @can('units.create')
            <x-button wire:click="createUnit" icon="plus">New unit</x-button>
        @endcan
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-card title="Units" :padding="false">
            <x-table>
                <x-slot:head>
                    <x-table.th>Name</x-table.th>
                    <x-table.th>Symbol</x-table.th>
                    <x-table.th align="center">Decimals</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
                </x-slot:head>
                @foreach ($units as $unit)
                    <tr wire:key="u-{{ $unit->id }}">
                        <x-table.td class="font-medium">{{ $unit->name }}</x-table.td>
                        <x-table.td class="font-mono">{{ $unit->symbol }}</x-table.td>
                        <x-table.td align="center">{{ $unit->allows_decimal ? 'Yes' : 'No' }}</x-table.td>
                        <x-table.td><x-status :status="$unit->is_active ? 'active' : 'inactive'" /></x-table.td>
                        <x-table.td align="right" class="whitespace-nowrap">
                            @can('units.edit')
                                <x-button variant="ghost" size="sm" wire:click="editUnit({{ $unit->id }})">Edit</x-button>
                            @endcan
                            @can('units.delete')
                                <x-button variant="ghost" size="sm" wire:click="deleteUnit({{ $unit->id }})" wire:confirm="Delete {{ $unit->name }}?">Delete</x-button>
                            @endcan
                        </x-table.td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <x-card title="Conversions" :padding="false">
            <x-slot:actions>
                @can('units.edit')
                    <x-button size="sm" variant="secondary" wire:click="createConversion" icon="plus">Add</x-button>
                @endcan
            </x-slot:actions>
            <x-table empty="No conversions yet.">
                <x-slot:head>
                    <x-table.th>Conversion</x-table.th>
                    <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
                </x-slot:head>
                @foreach ($conversions as $c)
                    <tr wire:key="c-{{ $c->id }}">
                        <x-table.td>1 {{ $c->fromUnit->name }} = @qty($c->factor) {{ $c->toUnit->name }}</x-table.td>
                        <x-table.td align="right" class="whitespace-nowrap">
                            @can('units.edit')
                                <x-button variant="ghost" size="sm" wire:click="editConversion({{ $c->id }})">Edit</x-button>
                                <x-button variant="ghost" size="sm" wire:click="deleteConversion({{ $c->id }})" wire:confirm="Delete this conversion?">Delete</x-button>
                            @endcan
                        </x-table.td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    </div>

    <x-modal wire:model="showUnit" :title="$unitId ? 'Edit unit' : 'New unit'">
        <form wire:submit="saveUnit" id="unit-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Name" wire:model="name" required />
            <x-input label="Symbol" wire:model="symbol" required />
            <x-checkbox wire:model="allows_decimal" label="Decimal quantities allowed" help="Off for pieces, pairs, boxes." class="sm:col-span-2" />
            <x-checkbox wire:model="is_active" label="Active" class="sm:col-span-2" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="unit-form">Save</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showConversion" title="Unit conversion">
        <form wire:submit="saveConversion" id="conversion-form" class="grid gap-4 sm:grid-cols-3">
            <x-select label="1 ×" wire:model="from_unit_id" :options="$unitOptions" placeholder="Unit…" required />
            <x-input label="equals" type="number" step="any" wire:model="factor" required />
            <x-select label="of" wire:model="to_unit_id" :options="$unitOptions" placeholder="Unit…" required />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="conversion-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
