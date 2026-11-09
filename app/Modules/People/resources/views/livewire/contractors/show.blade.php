<x-people::profile :record="$contractor" resource="contractors"
                   :edit-route="route('people.contractors.edit', $contractor)" :index-route="route('people.contractors.index')">
    <x-slot:details>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-people::detail label="Code" :value="$contractor->code" />
            <x-people::detail label="Contact person" :value="$contractor->contact_person" />
            <x-people::detail label="CNIC or NTN" :value="$contractor->cnic_ntn" />
            <x-people::detail label="Phone" :value="$contractor->phone" />
            <x-people::detail label="Work types" :value="implode(', ', $contractor->work_types ?? [])" class="sm:col-span-2" />
            <x-people::detail label="Address" :value="$contractor->address" class="sm:col-span-2" />
            <x-people::detail label="Notes" :value="$contractor->notes" class="sm:col-span-2 lg:col-span-3" />
        </dl>
    </x-slot:details>
    <x-slot:side>
        @if ($linkedVendor)
            <x-card title="Also a vendor">
                <a href="{{ route('people.vendors.show', $linkedVendor) }}" wire:navigate class="text-sm text-link hover:underline">{{ $linkedVendor->label() }}</a>
            </x-card>
        @endif
    </x-slot:side>
</x-people::profile>
