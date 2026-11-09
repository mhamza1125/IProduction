<div>
    <x-page-header :title="$job ? 'Edit '.$job->number : 'New contractor job'" description="Output lines are paid at the agreed rate for accepted pieces. Expected material is used to reconcile what the contractor holds." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-select label="Contractor" wire:model="contractor_id" :options="$contractors" placeholder="Choose…" required class="lg:col-span-2" />
                <x-date-input label="Job date" wire:model="job_date" required />
                <x-date-input label="Due date" wire:model="due_date" />
                <x-select label="Sales order line" wire:model="sales_order_line_id" :options="$orderLines" placeholder="None" class="lg:col-span-2" />
                <x-textarea label="Notes" wire:model="notes" class="lg:col-span-2" rows="2" />
            </div>
        </x-card>
        <x-card title="Output" :padding="false">
            <x-table>
                <x-slot:head><x-table.th>Item</x-table.th><x-table.th>Stage</x-table.th><x-table.th align="right">Agreed qty</x-table.th><x-table.th align="right">Rate (PKR)</x-table.th><x-table.th></x-table.th></x-slot:head>
                @foreach ($lines as $i => $l)
                    <tr wire:key="jl-{{ $i }}">
                        <x-table.td><x-select wire:model="lines.{{ $i }}.item_variant_id" :options="$variants" placeholder="Choose…" aria-label="Item" /></x-table.td>
                        <x-table.td><x-select wire:model="lines.{{ $i }}.stage_id" :options="$stages" placeholder="Finished" aria-label="Stage" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model="lines.{{ $i }}.quantity" aria-label="Quantity" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model="lines.{{ $i }}.rate" aria-label="Rate" /></x-table.td>
                        <x-table.td><x-button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $i }})" aria-label="Remove line" /></x-table.td>
                    </tr>
                @endforeach
            </x-table>
            <div class="border-t border-line p-3"><x-button size="sm" variant="secondary" icon="plus" wire:click="addLine">Add output</x-button></div>
        </x-card>
        <x-card title="Expected material use" :padding="false">
            <x-table empty="No material supplied by the company.">
                <x-slot:head><x-table.th>Material</x-table.th><x-table.th align="right">Expected for the whole job</x-table.th><x-table.th></x-table.th></x-slot:head>
                @foreach ($materials as $i => $m)
                    <tr wire:key="jm-{{ $i }}">
                        <x-table.td><x-select wire:model="materials.{{ $i }}.item_variant_id" :options="$variants" placeholder="Choose…" aria-label="Material" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model="materials.{{ $i }}.expected_qty" aria-label="Expected quantity" /></x-table.td>
                        <x-table.td><x-button size="sm" variant="ghost" icon="trash" wire:click="removeMaterial({{ $i }})" aria-label="Remove material" /></x-table.td>
                    </tr>
                @endforeach
            </x-table>
            <div class="border-t border-line p-3"><x-button size="sm" variant="secondary" icon="plus" wire:click="addMaterial">Add material</x-button></div>
        </x-card>
        <div class="flex justify-end gap-2">
            <x-button variant="ghost" :href="$job ? route('production.jobs.show', $job) : route('production.jobs.index')">Cancel</x-button>
            <x-button type="submit">Save draft</x-button>
        </div>
    </form>
</div>
