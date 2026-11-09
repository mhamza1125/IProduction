<div>
    <x-page-header :title="$invoice ? 'Edit '.$invoice->number : 'New invoice'" description="Invoice dispatched delivery lines of one customer and currency. Tax is optional per line (leave it empty for exports)." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-select label="Customer" wire:model.live="customer_id" :options="$customers" placeholder="Choose…" required class="lg:col-span-2" />
                <x-date-input label="Invoice date" wire:model="invoice_date" required />
                <x-date-input label="Due date" wire:model="due_date" />
                <x-input label="Payment terms" wire:model="payment_terms" />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
            </div>
        </x-card>
        @if ($customer_id)
            <x-card title="Dispatched, not yet invoiced" :padding="false">
                <x-table empty="Nothing dispatched is waiting to be invoiced.">
                    <x-slot:head><x-table.th>Invoice</x-table.th><x-table.th>Delivery · order</x-table.th><x-table.th>Item</x-table.th><x-table.th align="right">Qty</x-table.th><x-table.th align="right">Unit price</x-table.th><x-table.th align="right">Discount</x-table.th><x-table.th align="right">Tax %</x-table.th></x-slot:head>
                    @foreach ($available as $dl)
                        <tr wire:key="il-{{ $dl->id }}">
                            <x-table.td><input type="checkbox" wire:model="lines.{{ $dl->id }}.selected" aria-label="Invoice this line" class="size-4 rounded border-line-strong text-link"></x-table.td>
                            <x-table.td class="font-mono text-xs">{{ $dl->delivery->number }} · {{ $dl->orderLine->order->number }}</x-table.td>
                            <x-table.td>{{ $dl->variant->fullLabel() }}</x-table.td>
                            <x-table.td align="right">@qty(\App\Support\Decimal::sub($dl->quantity, $dl->returned_qty, 4))</x-table.td>
                            <x-table.td align="right"><input type="number" step="any" min="0" wire:model="lines.{{ $dl->id }}.unit_price" @disabled(! $canChangePrice) aria-label="Unit price" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong disabled:bg-hover bg-surface"></x-table.td>
                            <x-table.td align="right"><input type="number" step="any" min="0" wire:model="lines.{{ $dl->id }}.discount" aria-label="Discount" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></x-table.td>
                            <x-table.td align="right"><input type="number" step="any" min="0" max="100" wire:model="lines.{{ $dl->id }}.tax_rate" placeholder="none" aria-label="Tax rate" class="w-20 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></x-table.td>
                        </tr>
                    @endforeach
                </x-table>
                @error('lines') <p class="border-t border-line px-4 py-3 text-sm text-danger">{{ $message }}</p> @enderror
            </x-card>
        @endif
        <div class="flex justify-end gap-2">
            <x-button variant="ghost" :href="$invoice ? route('delivery.invoices.show', $invoice) : route('delivery.invoices.index')">Cancel</x-button>
            <x-button type="submit">Save draft</x-button>
        </div>
    </form>
</div>
