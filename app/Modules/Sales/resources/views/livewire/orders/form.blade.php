<div>
    <x-page-header :title="$order ? 'Edit '.$order->number : 'New sales order'" :description="$order && ! $order->isDraft() ? 'Approved order: quantities cannot go below what was delivered; lines with stock activity cannot be removed.' : null" />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card class="max-w-5xl">
            <div class="grid gap-4 sm:grid-cols-4">
                <x-select label="Customer" wire:model.live="customer_id" :options="$customers" placeholder="Choose…" required class="sm:col-span-2" :disabled="$order && ! $order->isDraft()" />
                <x-date-input label="Order date" wire:model="order_date" required />
                <x-date-input label="Due date" wire:model="due_date" />
                <x-input label="Customer PO no." wire:model="customer_po_number" />
                <x-input label="Payment terms" wire:model="payment_terms" />
                <x-input label="Currency" :value="$currency" disabled help="From the customer." />
                <x-checkbox wire:model="sells_rejected_stock" label="Sale of rejected stock" help="Delivered from Rejected status; separate revenue." />
                <x-textarea label="Shipping address" wire:model="shipping_address" rows="2" class="sm:col-span-2" />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="sm:col-span-2" />
            </div>
        </x-card>
        <x-card title="Lines" :padding="false">
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900/60">
                        <tr><th class="px-3 py-2 text-left">Item</th><th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2 text-right">Unit price ({{ $currency }})</th><th class="px-3 py-2 text-right">Tax %</th><th class="px-3 py-2 text-right">Line total</th><th class="px-3 py-2 text-left">Due</th><th class="px-3 py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($lines as $i => $line)
                            <tr wire:key="sol-{{ $i }}-{{ $line['id'] ?? 'n' }}" class="align-top">
                                <td class="px-3 py-2">
                                    <select wire:model.live="lines.{{ $i }}.item_variant_id" aria-label="Item" class="w-80 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <option value="">Choose…</option>@foreach ($variants as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                                    </select>
                                    @error("lines.$i.item_variant_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    <input type="text" wire:model="lines.{{ $i }}.description" placeholder="Description (optional)" aria-label="Description" class="mt-1 block w-80 rounded-md border-0 py-1 text-xs ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700">
                                </td>
                                <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model.blur="lines.{{ $i }}.quantity" aria-label="Quantity" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">@error("lines.$i.quantity") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</td>
                                <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model.blur="lines.{{ $i }}.unit_price" aria-label="Unit price" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">@error("lines.$i.unit_price") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</td>
                                <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model="lines.{{ $i }}.tax_rate" aria-label="Tax rate" class="w-16 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"></td>
                                <td class="px-3 py-2 text-right tabular">{{ is_numeric($line['quantity']) && is_numeric($line['unit_price']) ? \App\Support\Format::money(\App\Support\Decimal::mul($line['quantity'], $line['unit_price'])) : '' }}</td>
                                <td class="px-3 py-2"><x-date-input wire:model="lines.{{ $i }}.due_date" class="w-32" /></td>
                                <td class="px-3 py-2 text-right"><button type="button" wire:click="removeLine({{ $i }})" class="p-1 text-zinc-400 hover:text-red-600" aria-label="Remove"><x-icon name="trash" class="size-4" /></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('lines') <p class="px-4 py-2 text-sm text-red-600">{{ $message }}</p> @enderror
            <x-slot:footer>
                <x-button variant="secondary" size="sm" wire:click="addLine" icon="plus" class="mr-auto">Add line</x-button>
                <span class="text-sm font-semibold">Total {{ $currency }} {{ \App\Support\Format::money($total) }}</span>
                <x-button variant="secondary" :href="$order ? route('sales.orders.show', $order) : route('sales.orders.index')">Cancel</x-button>
                <x-button type="submit">Save</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
