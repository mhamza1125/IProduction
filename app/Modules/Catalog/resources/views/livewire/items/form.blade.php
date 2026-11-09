<div>
    <x-page-header :title="$item ? 'Edit item' : 'New item'" :description="$item?->code" />

    <form wire:submit="save" data-save-shortcut class="max-w-4xl space-y-6">
        <x-card title="Item">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Code" wire:model="code" :help="$item ? null : 'Leave empty to number automatically (ITM-0001).'" />
                <x-select label="Type" wire:model.live="type" :options="$types" required />
                <x-input label="Name" wire:model="name" required autofocus class="sm:col-span-2" />
                <x-select label="Category" wire:model="category_id" :options="$categories" placeholder="—" />
                <x-input label="Minimum stock" type="number" step="any" min="0" wire:model="min_stock" />
                <x-textarea label="Description" wire:model="description" rows="2" class="sm:col-span-2" />
                <div class="flex flex-wrap gap-x-6 gap-y-2 sm:col-span-2">
                    <x-checkbox wire:model="can_buy" label="Can be bought" />
                    <x-checkbox wire:model="can_make" label="Can be made" />
                    <x-checkbox wire:model="can_sell" label="Can be sold" />
                    <x-checkbox wire:model="track_by_lot" label="Track by lot" />
                </div>
                @error('can_buy') <p class="text-xs text-danger sm:col-span-2">{{ $message }}</p> @enderror
                <p class="text-xs text-subtle sm:col-span-2">An item can be both bought and made; whether to make or buy is decided when planning each order line.</p>
            </div>
        </x-card>

        <x-card title="Units">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-select label="Stock unit" wire:model="stock_unit_id" :options="$units" required :disabled="(bool) $item" :help="$item ? 'Fixed after creation.' : null" />
                <x-select label="Purchase unit" wire:model="purchase_unit_id" :options="$units" placeholder="Same as stock" />
                <x-select label="Sales unit" wire:model="sales_unit_id" :options="$units" placeholder="Same as stock" />
            </div>
            <p class="mt-2 text-xs text-subtle">Conversions between units are set in Settings › Units, or on this item after saving.</p>
        </x-card>

        <x-card title="Prices and tax">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-input label="Default selling price" type="number" step="any" min="0" wire:model="default_selling_price" />
                @if ($canSeeCost)
                    <x-input label="Default purchase cost (PKR)" type="number" step="any" min="0" wire:model="default_purchase_cost" />
                @endif
                <x-input label="Over-receipt tolerance (%)" type="number" step="0.01" min="0" max="100" wire:model="over_receipt_percent" help="Empty = vendor or company default." />
                <x-input label="Tax rate (%)" type="number" step="0.01" min="0" max="100" wire:model="tax_rate" />
                <x-input label="Tax notes" wire:model="tax_notes" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="Image">
            <div class="flex items-center gap-4">
                @if ($image)
                    <img src="{{ $image->temporaryUrl() }}" alt="" class="h-16 w-16 rounded object-cover">
                @elseif ($item?->image_path)
                    <img src="{{ Storage::disk('public')->url($item->image_path) }}" alt="" class="h-16 w-16 rounded object-cover">
                @endif
                <input type="file" wire:model="image" accept="image/*" class="text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-hover file:px-3 file:py-1.5 file:text-sm">
                @error('image') <p class="text-xs text-danger">{{ $message }}</p> @enderror
            </div>
        </x-card>

        <x-card>
            <x-checkbox wire:model="is_active" label="Active" help="Inactive items cannot be used on new documents." />
            <x-slot:footer>
                <x-button variant="secondary" :href="$item ? route('catalog.items.show', $item) : route('catalog.items.index')">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
