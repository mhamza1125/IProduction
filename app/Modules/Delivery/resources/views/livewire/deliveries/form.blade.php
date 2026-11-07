<div>
    <x-page-header :title="$delivery ? 'Edit '.$delivery->number : 'New delivery'" description="Choose a customer, then take quantities from any of their open order lines. All lines share one customer and currency." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-select label="Customer" wire:model.live="customer_id" :options="$customers" placeholder="Choose…" required class="lg:col-span-2" />
                <x-date-input label="Delivery date" wire:model="delivery_date" required />
                <x-input label="Shipment method" wire:model="shipment_method" placeholder="Sea, air, courier…" />
                <x-input label="Vehicle or courier" wire:model="vehicle" />
                <x-input label="Tracking number" wire:model="tracking_number" />
                <x-textarea label="Shipping address" wire:model="shipping_address" rows="2" class="lg:col-span-2" />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-4" />
            </div>
        </x-card>
        @if ($customer)
            <x-card title="Open order lines" :padding="false">
                <x-slot:actions><x-button size="sm" variant="secondary" wire:click="fillRemaining">Take what is ready</x-button></x-slot:actions>
                <x-table empty="This customer has no open order lines.">
                    <x-slot:head><x-table.th>Order</x-table.th><x-table.th>Item</x-table.th><x-table.th align="right">Still to deliver</x-table.th><x-table.th align="right">Reserved</x-table.th><x-table.th align="right">Ready</x-table.th><x-table.th align="right">Deliver now</x-table.th><x-table.th>Reason if more</x-table.th></x-slot:head>
                    @foreach ($rows as $r)
                        @php($l = $r['line'])
                        <tr wire:key="ol-{{ $l->id }}">
                            <x-table.td class="font-mono text-xs">{{ $l->order->number }} @if ($l->order->sells_rejected_stock)<x-badge color="red">rejected stock</x-badge>@endif</x-table.td>
                            <x-table.td>{{ $l->variant->fullLabel() }}</x-table.td>
                            <x-table.td align="right">@qty($r['remaining'])@if ((float) $r['on_deliveries'])<div class="text-xs text-zinc-500">+ @qty($r['on_deliveries']) on other deliveries</div>@endif</x-table.td>
                            <x-table.td align="right">@qty($r['reserved'])</x-table.td>
                            <x-table.td align="right">@qty($r['ready'])</x-table.td>
                            <x-table.td align="right"><input type="number" step="any" min="0" wire:model="quantities.{{ $l->id }}" aria-label="Quantity" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"></x-table.td>
                            <x-table.td><input wire:model="overReasons.{{ $l->id }}" aria-label="Reason" class="w-48 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"></x-table.td>
                        </tr>
                    @endforeach
                </x-table>
                @error('quantities') <p class="border-t border-zinc-200 px-4 py-3 text-sm text-red-600 dark:border-zinc-800">{{ $message }}</p> @enderror
            </x-card>
        @endif
        <div class="flex justify-end gap-2">
            <x-button variant="ghost" :href="$delivery ? route('delivery.deliveries.show', $delivery) : route('delivery.deliveries.index')">Cancel</x-button>
            <x-button type="submit">Save draft</x-button>
        </div>
    </form>
</div>
