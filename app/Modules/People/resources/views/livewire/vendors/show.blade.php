<x-people::profile :record="$vendor" resource="vendors"
                   :edit-route="route('people.vendors.edit', $vendor)" :index-route="route('people.vendors.index')">
    <x-slot:details>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-people::detail label="Code" :value="$vendor->code" />
            <x-people::detail label="Contact person" :value="$vendor->contact_person" />
            <x-people::detail label="Phone" :value="$vendor->phone" />
            <x-people::detail label="Email" :value="$vendor->email" />
            <x-people::detail label="NTN" :value="$vendor->ntn" />
            <x-people::detail label="Payment terms" :value="$vendor->payment_terms" />
            <x-people::detail label="Over-receipt tolerance" :value="$vendor->over_receipt_percent !== null ? $vendor->over_receipt_percent.'%' : 'Company default'" />
            <x-people::detail label="Address" :value="$vendor->address" class="sm:col-span-2" />
            <x-people::detail label="Notes" :value="$vendor->notes" class="sm:col-span-2 lg:col-span-3" />
        </dl>
    </x-slot:details>
    <x-slot:side>
        @includeIf('purchasing::partials.vendor-summary', ['vendor' => $vendor])
        @if ($linkedContractor)
            <x-card title="Also a contractor">
                <a href="{{ route('people.contractors.show', $linkedContractor) }}" wire:navigate class="text-sm text-brand-600 hover:underline">{{ $linkedContractor->label() }}</a>
            </x-card>
        @endif
    </x-slot:side>
</x-people::profile>
