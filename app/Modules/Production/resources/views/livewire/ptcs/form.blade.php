<div>
    <x-page-header :title="$ptc ? 'Edit '.$ptc->number : 'New PTC'" description="One PTC tracks one variant. Approving it copies the route and BOM and creates the output lot." />
    <form wire:submit="save" data-save-shortcut>
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-select label="Item to make" wire:model.live="item_variant_id" :options="$variants" placeholder="Choose…" required class="lg:col-span-2" />
                <x-input label="Planned quantity" type="number" step="any" min="0" wire:model="planned_qty" required />
                <x-select label="Route" wire:model.live="route_id" :options="$routes" placeholder="Choose…" required />
                <x-select label="Start stage" wire:model="start_stage_id" :options="$stages" placeholder="First stage" help="Start part-way, e.g. with bought part-made stock." />
                <x-select label="End stage" wire:model="end_stage_id" :options="$stages" placeholder="Last stage" />
                <x-select label="Sales order line" wire:model="sales_order_line_id" :options="$lines" placeholder="For stock (no order)" class="lg:col-span-2" />
                <x-select label="Responsible" wire:model="responsible_employee_id" :options="$employees" placeholder="—" />
                <x-date-input label="Start date" wire:model="start_date" required />
                <x-date-input label="Target date" wire:model="target_date" />
                <x-textarea label="Notes" wire:model="notes" class="sm:col-span-2 lg:col-span-3" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="ghost" :href="$ptc ? route('production.ptcs.show', $ptc) : route('production.ptcs.index')">Cancel</x-button>
                <x-button type="submit">Save draft</x-button>
            </div>
        </x-card>
    </form>
</div>
