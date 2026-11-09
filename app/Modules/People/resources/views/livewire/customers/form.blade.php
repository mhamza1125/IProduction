<div>
    <x-page-header :title="$customer ? 'Edit customer' : 'New customer'" :description="$customer?->code" />

    <form wire:submit="save" data-save-shortcut class="max-w-4xl space-y-6">
        <x-card title="Customer">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Code" wire:model="code" :help="$customer ? null : 'Leave empty to number automatically.'" />
                <x-select label="Currency" wire:model="currency_code" :options="$currencies" placeholder="Choose…" required :disabled="$currencyLocked"
                          :help="$currencyLocked ? 'Fixed: this customer already has transactions.' : 'Invoices and the ledger use this currency.'" />
                <x-input label="Name" wire:model="name" required autofocus />
                <x-input label="Company" wire:model="company" />
                <x-input label="Email" type="email" wire:model="email" />
                <x-input label="Phone" wire:model="phone" />
                <x-input label="Country" wire:model="country" />
                <x-input label="NTN" wire:model="ntn" />
                <x-input label="Payment terms" wire:model="payment_terms" placeholder="e.g. 30% advance, balance before shipment" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="Addresses">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-textarea label="Billing address" wire:model="billing_address" rows="3" />
                <div>
                    <x-textarea label="Shipping address" wire:model="shipping_address" rows="3" />
                    <button type="button" wire:click="copyBillingToShipping" class="mt-1 text-xs text-link hover:underline">Same as billing</button>
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="space-y-4">
                <x-textarea label="Notes" wire:model="notes" rows="2" />
                <x-checkbox wire:model="is_active" label="Active" help="Inactive customers cannot be used on new documents." />
            </div>
            <x-slot:footer>
                <x-button variant="secondary" :href="$customer ? route('people.customers.show', $customer) : route('people.customers.index')">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
