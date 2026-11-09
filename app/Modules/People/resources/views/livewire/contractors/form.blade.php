<div>
    <x-page-header :title="$contractor ? 'Edit contractor' : 'New contractor'" :description="$contractor?->code" />

    <form wire:submit="save" data-save-shortcut class="max-w-4xl space-y-6">
        <x-card title="Contractor">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Code" wire:model="code" :help="$contractor ? null : 'Leave empty to number automatically.'" />
                <x-input label="Name" wire:model="name" required autofocus />
                <x-input label="Contact person" wire:model="contact_person" />
                <x-input label="CNIC or NTN" wire:model="cnic_ntn" />
                <x-input label="Phone" wire:model="phone" />
                <x-textarea label="Address" wire:model="address" rows="2" class="sm:col-span-2" />
                <div class="sm:col-span-2">
                    <p class="mb-2 text-sm font-medium text-muted">Work types</p>
                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                        @foreach ($stages as $stage)
                            <x-checkbox wire:model="work_types" value="{{ $stage }}" :label="$stage" />
                        @endforeach
                    </div>
                    @error('work_types.*') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>
                @unless ($contractor)
                    <x-select label="Same business as vendor" wire:model="linked_vendor_id" :options="$vendors" placeholder="Not a vendor"
                              help="Link if this contractor is also a supplier. Their ledgers can then be seen together." class="sm:col-span-2" />
                @endunless
            </div>
        </x-card>

        <x-card>
            <div class="space-y-4">
                <x-textarea label="Notes" wire:model="notes" rows="2" />
                <x-checkbox wire:model="is_active" label="Active" help="Inactive contractors cannot be given new jobs." />
            </div>
            <x-slot:footer>
                <x-button variant="secondary" :href="$contractor ? route('people.contractors.show', $contractor) : route('people.contractors.index')">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
