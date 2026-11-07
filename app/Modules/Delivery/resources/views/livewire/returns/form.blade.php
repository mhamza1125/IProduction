<div>
    <x-page-header :title="$return ? 'Edit '.$return->number : 'New delivery return'" description="Returned goods come back in Returned status for inspection. A replacement reopens the order line." />
    <form wire:submit="save" data-save-shortcut>
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-select label="Customer" wire:model.live="customer_id" :options="$customers" placeholder="Choose…" required />
                <x-select label="Delivery line" wire:model.live="delivery_line_id" :options="$lines" placeholder="Choose…" required class="lg:col-span-2" />
                <x-select label="Lot" wire:model="lot_id" :options="$lots" placeholder="First lot of the line" />
                <x-input label="Quantity" type="number" step="any" min="0" wire:model="quantity" required />
                <x-date-input label="Return date" wire:model="return_date" required />
                <x-input label="Reason" wire:model="reason" required class="lg:col-span-2" />
                <x-checkbox wire:model="replacement_required" label="Replacement required (reopens the order line)" />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="ghost" :href="$return ? route('delivery.returns.show', $return) : route('delivery.returns.index')">Cancel</x-button>
                <x-button type="submit">Save draft</x-button>
            </div>
        </x-card>
    </form>
</div>
