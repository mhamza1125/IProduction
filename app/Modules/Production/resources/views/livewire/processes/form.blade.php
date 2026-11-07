<div>
    <x-page-header :title="$process ? 'Edit '.$process->number : 'New material processing'" description="Output cost = input cost + labor − recoverable value, shared over the outputs by quantity. Record the actual yield." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-input label="Process" wire:model="process_name" required class="lg:col-span-2" placeholder="Cut thaan into pieces" />
                <x-date-input label="Date" wire:model="process_date" required />
                <x-select label="Worker" wire:model.live="worker_type" :options="['employee' => 'Employee', 'contractor' => 'Contractor']" placeholder="In-house, no labor charge" />
                @if ($worker_type === 'employee') <x-select label="Employee" wire:model="employee_id" :options="$employees" placeholder="Choose…" /> @endif
                @if ($worker_type === 'contractor') <x-select label="Contractor" wire:model="contractor_id" :options="$contractors" placeholder="Choose…" /> @endif
                <x-input label="Labor / contractor cost (PKR)" type="number" step="any" min="0" wire:model="labor_cost" />
                <x-input label="Recoverable value (PKR)" type="number" step="any" min="0" wire:model="recoverable_value" help="Off-cuts or scrap with resale value." />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="Inputs" :padding="false">
            <x-table>
                <x-slot:head><x-table.th>Material</x-table.th><x-table.th>Lot ID (optional)</x-table.th><x-table.th align="right">Quantity</x-table.th><x-table.th></x-table.th></x-slot:head>
                @foreach ($inputs as $i => $l)
                    <tr wire:key="in-{{ $i }}">
                        <x-table.td><x-select wire:model="inputs.{{ $i }}.item_variant_id" :options="$variants" placeholder="Choose…" aria-label="Material" /></x-table.td>
                        <x-table.td><x-input type="number" wire:model="inputs.{{ $i }}.lot_id" aria-label="Lot" placeholder="FIFO" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model="inputs.{{ $i }}.quantity" aria-label="Quantity" /></x-table.td>
                        <x-table.td><x-button size="sm" variant="ghost" icon="trash" wire:click="remove('input', {{ $i }})" aria-label="Remove" /></x-table.td>
                    </tr>
                @endforeach
            </x-table>
            <div class="border-t border-zinc-200 p-3 dark:border-zinc-800"><x-button size="sm" variant="secondary" icon="plus" wire:click="add('input')">Add input</x-button></div>
        </x-card>

        <x-card title="Outputs" :padding="false">
            <x-table>
                <x-slot:head><x-table.th>Material</x-table.th><x-table.th align="right">Expected</x-table.th><x-table.th align="right">Actual</x-table.th><x-table.th>Location</x-table.th><x-table.th></x-table.th></x-slot:head>
                @foreach ($outputs as $i => $l)
                    <tr wire:key="out-{{ $i }}">
                        <x-table.td><x-select wire:model="outputs.{{ $i }}.item_variant_id" :options="$variants" placeholder="Choose…" aria-label="Output" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model="outputs.{{ $i }}.expected_qty" aria-label="Expected" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model="outputs.{{ $i }}.quantity" aria-label="Actual" /></x-table.td>
                        <x-table.td><x-select wire:model="outputs.{{ $i }}.location_id" :options="$locations" placeholder="Main Store" aria-label="Location" /></x-table.td>
                        <x-table.td><x-button size="sm" variant="ghost" icon="trash" wire:click="remove('output', {{ $i }})" aria-label="Remove" /></x-table.td>
                    </tr>
                @endforeach
            </x-table>
            <div class="border-t border-zinc-200 p-3 dark:border-zinc-800"><x-button size="sm" variant="secondary" icon="plus" wire:click="add('output')">Add output</x-button></div>
        </x-card>

        <x-card title="Losses" :padding="false">
            <x-table empty="No losses recorded.">
                <x-slot:head><x-table.th>Type</x-table.th><x-table.th align="right">Quantity (input units)</x-table.th><x-table.th>Reason</x-table.th><x-table.th></x-table.th></x-slot:head>
                @foreach ($losses as $i => $l)
                    <tr wire:key="loss-{{ $i }}">
                        <x-table.td><x-select wire:model="losses.{{ $i }}.loss_type" :options="['wastage' => 'Wastage', 'rejected' => 'Rejected']" aria-label="Type" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model="losses.{{ $i }}.quantity" aria-label="Quantity" /></x-table.td>
                        <x-table.td><x-input wire:model="losses.{{ $i }}.reason" aria-label="Reason" /></x-table.td>
                        <x-table.td><x-button size="sm" variant="ghost" icon="trash" wire:click="remove('loss', {{ $i }})" aria-label="Remove" /></x-table.td>
                    </tr>
                @endforeach
            </x-table>
            <div class="border-t border-zinc-200 p-3 dark:border-zinc-800"><x-button size="sm" variant="secondary" icon="plus" wire:click="add('loss')">Add loss</x-button></div>
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button variant="ghost" :href="$process ? route('production.processes.show', $process) : route('production.processes.index')">Cancel</x-button>
            <x-button type="submit" variant="secondary">Save draft</x-button>
            @can('material_processes.post') @can('material_processes.approve')
                <x-button type="button" wire:click="saveAndPost" wire:confirm="Save, approve and post now? Stock moves and the labor cost is posted.">Save and post</x-button>
            @endcan @endcan
        </div>
    </form>
</div>
