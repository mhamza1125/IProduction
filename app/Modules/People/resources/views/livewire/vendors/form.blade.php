<div>
    <x-page-header :title="$vendor ? 'Edit vendor' : 'New vendor'" :description="$vendor?->code" />

    <form wire:submit="save" data-save-shortcut class="max-w-4xl space-y-6">
        <x-card title="Vendor">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Code" wire:model="code" :help="$vendor ? null : 'Leave empty to number automatically.'" />
                <x-input label="Name" wire:model="name" required autofocus />
                <x-input label="Contact person" wire:model="contact_person" />
                <x-input label="Phone" wire:model="phone" />
                <x-input label="Email" type="email" wire:model="email" />
                <x-input label="NTN" wire:model="ntn" />
                <x-textarea label="Address" wire:model="address" rows="2" class="sm:col-span-2" />
                <x-input label="Payment terms" wire:model="payment_terms" placeholder="e.g. 15 days" />
                <x-input label="Over-receipt tolerance (%)" type="number" step="0.01" min="0" max="100" wire:model="over_receipt_percent"
                         help="Leave empty to use the company default." />
                @unless ($vendor)
                    <x-select label="Same business as contractor" wire:model="linked_contractor_id" :options="$contractors" placeholder="Not a contractor"
                              help="Link if this vendor also takes contractor jobs. Their ledgers can then be seen together." class="sm:col-span-2" />
                @endunless
            </div>
        </x-card>

        <x-card>
            <div class="space-y-4">
                <x-textarea label="Notes" wire:model="notes" rows="2" />
                <x-checkbox wire:model="is_active" label="Active" help="Inactive vendors cannot be used on new documents." />
            </div>
            <x-slot:footer>
                <x-button variant="secondary" :href="$vendor ? route('people.vendors.show', $vendor) : route('people.vendors.index')">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
