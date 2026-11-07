<div>
    <x-page-header :title="$asset ? $asset->code.' · '.$asset->name : 'New asset'" description="Record only: it does not post to accounts or stock." />
    <div class="grid gap-6 xl:grid-cols-3">
        <form wire:submit="save" data-save-shortcut class="xl:col-span-2">
            <x-card>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-input label="Code" wire:model="code" placeholder="Automatic" />
                    <x-input label="Name" wire:model="name" required class="lg:col-span-2" />
                    <x-input label="Category" wire:model="category" placeholder="Vehicle, AC, computer, land…" />
                    <x-date-input label="Purchase date" wire:model="purchase_date" />
                    <x-input label="Value (PKR)" type="number" step="any" min="0" wire:model="value" />
                    <x-input label="Location" wire:model="location" />
                    <x-select label="Assigned to" wire:model="assigned_employee_id" :options="$employees" placeholder="—" />
                    <x-input label="Serial number" wire:model="serial_number" />
                    <x-select label="Status" wire:model="status" :options="\App\Modules\Finance\Models\Asset::STATUSES" />
                    <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    @if ($asset) @can('assets.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Remove this asset from the register?">Remove</x-button> @endcan @endif
                    <x-button variant="ghost" :href="route('finance.assets.index')">Back</x-button>
                    <x-button type="submit">Save</x-button>
                </div>
            </x-card>
        </form>
        @if ($asset)
            <div class="space-y-6">
                <x-card title="Attachments"><livewire:core::shared.attachments :model="$asset" :key="'a-'.$asset->id" /></x-card>
                <x-card title="Notes"><livewire:core::shared.notes :model="$asset" :key="'n-'.$asset->id" /></x-card>
                <x-card title="History"><livewire:core::shared.activity-history :model="$asset" :key="'h-'.$asset->id" /></x-card>
            </div>
        @endif
    </div>
</div>
