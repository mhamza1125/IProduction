<x-people::profile :record="$customer" resource="customers" :subtitle="$customer->currency_code"
                   :edit-route="route('people.customers.edit', $customer)" :index-route="route('people.customers.index')">
    <x-slot:details>
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-people::detail label="Code" :value="$customer->code" />
            <x-people::detail label="Currency" :value="$customer->currency_code" />
            <x-people::detail label="Company" :value="$customer->company" />
            <x-people::detail label="Email" :value="$customer->email" />
            <x-people::detail label="Phone" :value="$customer->phone" />
            <x-people::detail label="Country" :value="$customer->country" />
            <x-people::detail label="NTN" :value="$customer->ntn" />
            <x-people::detail label="Payment terms" :value="$customer->payment_terms" class="sm:col-span-2" />
            <x-people::detail label="Billing address" :value="$customer->billing_address" />
            <x-people::detail label="Shipping address" :value="$customer->shipping_address" />
            <x-people::detail label="Notes" :value="$customer->notes" class="sm:col-span-2 lg:col-span-3" />
        </dl>
    </x-slot:details>
</x-people::profile>
