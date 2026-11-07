<div>
    <x-page-header :title="$transfer ? 'Edit '.$transfer->number : 'New transfer'" description="Move money between two company accounts of the same currency. For different currencies use a Currency Conversion." />
    <form wire:submit="save" data-save-shortcut>
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-date-input label="Date" wire:model="transfer_date" required />
                <x-select label="From" wire:model="from_account_id" :options="$accounts" placeholder="Choose…" required />
                <x-select label="To" wire:model="to_account_id" :options="$accounts" placeholder="Choose…" required />
                <x-input label="Amount" type="number" step="any" min="0" wire:model="amount" required />
                <x-input label="Reference" wire:model="reference" />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="ghost" :href="$transfer ? route('finance.transfers.show', $transfer) : route('finance.transfers.index')">Cancel</x-button>
                <x-button type="submit">Save draft</x-button>
            </div>
        </x-card>
    </form>
</div>
