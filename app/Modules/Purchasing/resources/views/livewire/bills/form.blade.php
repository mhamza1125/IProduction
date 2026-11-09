<div>
    <x-page-header :title="$bill ? 'Edit '.$bill->number : 'New vendor bill'" description="Bill the vendor's posted receipts. Billed quantity cannot exceed accepted quantity unless approved." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card class="max-w-5xl">
            <div class="grid gap-4 sm:grid-cols-4">
                <x-select label="Vendor" wire:model.live="vendor_id" :options="$vendors" placeholder="Choose…" required class="sm:col-span-2" :disabled="(bool) $bill" />
                <x-input label="Vendor invoice no." wire:model="vendor_invoice_number" required />
                <x-date-input label="Bill date" wire:model="bill_date" required />
                <x-date-input label="Due date" wire:model="due_date" />
                <x-input label="Tax" type="number" step="0.01" min="0" wire:model="tax_amount" />
                <x-input label="Charges" type="number" step="0.01" min="0" wire:model="charges_amount" />
                <x-input label="Deductions" type="number" step="0.01" min="0" wire:model="deductions_amount" />
                <x-input label="Notes" wire:model="notes" class="sm:col-span-4" />
            </div>
        </x-card>
        <x-card title="Received lines to bill" :padding="false">
            <x-table empty="Choose a vendor with posted receipts that are not fully billed.">
                <x-slot:head><x-table.th>Receipt</x-table.th><x-table.th>Item</x-table.th><x-table.th align="right">Payable</x-table.th><x-table.th align="right">Billed</x-table.th><x-table.th align="right">Rate</x-table.th><x-table.th align="right">Bill now</x-table.th></x-slot:head>
                @foreach ($openLines as $l)
                    <tr wire:key="bl-{{ $l->id }}">
                        <x-table.td class="font-mono text-xs">{{ $l->receipt->number }}</x-table.td>
                        <x-table.td>{{ $l->variant->fullLabel() }}</x-table.td>
                        <x-table.td align="right">@qty($l->payable_qty) {{ $l->orderLine->unit->symbol }}</x-table.td>
                        <x-table.td align="right">@qty($l->billed_qty)</x-table.td>
                        <x-table.td align="right">@money($l->rate, null, 4)</x-table.td>
                        <x-table.td align="right">
                            <input type="number" step="any" min="0" wire:model="quantities.{{ $l->id }}" aria-label="Quantity to bill" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface">
                            @error("quantities.{$l->id}") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                        </x-table.td>
                    </tr>
                @endforeach
            </x-table>
            @error('quantities') <p class="px-4 py-2 text-sm text-danger">{{ $message }}</p> @enderror
            <x-slot:footer>
                <x-button variant="secondary" :href="$bill ? route('purchasing.bills.show', $bill) : route('purchasing.bills.index')">Cancel</x-button>
                <x-button type="submit">Save draft</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
