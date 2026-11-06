<div>
    <x-page-header :title="$order ? 'Edit '.$order->number : 'New purchase order'" />

    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card class="max-w-5xl">
            <div class="grid gap-4 sm:grid-cols-4">
                <x-select label="Vendor" wire:model.live="vendor_id" :options="$vendors" placeholder="Choose…" required class="sm:col-span-2" />
                <x-date-input label="Order date" wire:model="order_date" required />
                <x-date-input label="Expected date" wire:model="expected_date" />
                <x-input label="Payment terms" wire:model="payment_terms" class="sm:col-span-2" />
                <x-input label="Notes" wire:model="notes" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="Lines" :padding="false">
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left">Item</th><th class="px-3 py-2 text-left">Stage (part-made)</th>
                            <th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2 text-left">Unit</th><th class="px-3 py-2 text-right">Rate (PKR)</th>
                            <th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2 text-left">Expected</th><th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($lines as $i => $line)
                            <tr wire:key="pol-{{ $i }}" class="align-top">
                                <td class="px-3 py-2">
                                    <select wire:model.live="lines.{{ $i }}.item_variant_id" aria-label="Item" class="w-72 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <option value="">Choose…</option>
                                        @foreach ($variants as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                                    </select>
                                    @error("lines.$i.item_variant_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    <input type="text" wire:model="lines.{{ $i }}.description" placeholder="Description (optional)" aria-label="Description" class="mt-1 block w-72 rounded-md border-0 py-1 text-xs ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700">
                                </td>
                                <td class="px-3 py-2"><select wire:model="lines.{{ $i }}.stage_id" aria-label="Stage" class="w-32 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"><option value="">—</option>@foreach ($stages as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></td>
                                <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model.blur="lines.{{ $i }}.quantity" aria-label="Quantity" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">@error("lines.$i.quantity") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</td>
                                <td class="px-3 py-2"><select wire:model="lines.{{ $i }}.unit_id" aria-label="Unit" class="w-24 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">@foreach ($units as $id => $s)<option value="{{ $id }}">{{ $s }}</option>@endforeach</select></td>
                                <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model.blur="lines.{{ $i }}.rate" aria-label="Rate" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">@error("lines.$i.rate") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror</td>
                                <td class="px-3 py-2 text-right tabular">{{ is_numeric($line['quantity']) && is_numeric($line['rate']) ? \App\Support\Format::money(\App\Support\Decimal::mul($line['quantity'], $line['rate'])) : '' }}</td>
                                <td class="px-3 py-2"><x-date-input wire:model="lines.{{ $i }}.expected_date" class="w-32" /></td>
                                <td class="px-3 py-2 text-right"><button type="button" wire:click="removeLine({{ $i }})" class="p-1 text-zinc-400 hover:text-red-600" aria-label="Remove"><x-icon name="trash" class="size-4" /></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('lines') <p class="px-4 py-2 text-sm text-red-600">{{ $message }}</p> @enderror
            <x-slot:footer>
                <x-button variant="secondary" size="sm" wire:click="addLine" icon="plus" class="mr-auto">Add line</x-button>
                <span class="text-sm font-semibold">Total PKR {{ \App\Support\Format::money($total) }}</span>
                <x-button variant="secondary" :href="$order ? route('purchasing.orders.show', $order) : route('purchasing.orders.index')">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Save draft</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
