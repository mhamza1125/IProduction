<div>
    <x-page-header :title="$note ? 'Edit '.$note->number : 'New credit note'" description="Reduces what the customer owes, in their currency. Linked to an invoice, it reduces that invoice's balance; any rest stays as customer credit." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-select label="Customer" wire:model.live="customer_id" :options="$customers" placeholder="Choose…" required class="lg:col-span-2" />
                <x-select label="Invoice" wire:model="invoice_id" :options="$invoices" placeholder="None (customer credit)" />
                <x-date-input label="Date" wire:model="credit_date" required />
                <x-input label="Reason" wire:model="reason" required class="lg:col-span-2" />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-2" />
            </div>
        </x-card>
        <x-card title="Lines" :padding="false">
            <x-table>
                <x-slot:head><x-table.th>Description</x-table.th><x-table.th align="right">Amount</x-table.th><x-table.th align="right">Tax %</x-table.th><x-table.th></x-table.th></x-slot:head>
                @foreach ($lines as $i => $l)
                    <tr wire:key="cl-{{ $i }}">
                        <x-table.td><x-input wire:model="lines.{{ $i }}.description" aria-label="Description" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model="lines.{{ $i }}.amount" aria-label="Amount" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" max="100" wire:model="lines.{{ $i }}.tax_rate" placeholder="none" aria-label="Tax rate" /></x-table.td>
                        <x-table.td><x-button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $i }})" aria-label="Remove line" /></x-table.td>
                    </tr>
                @endforeach
            </x-table>
            <div class="border-t border-line p-3"><x-button size="sm" variant="secondary" icon="plus" wire:click="addLine">Add line</x-button></div>
        </x-card>
        <div class="flex justify-end gap-2">
            <x-button variant="ghost" :href="$note ? route('delivery.credit-notes.show', $note) : route('delivery.credit-notes.index')">Cancel</x-button>
            <x-button type="submit">Save draft</x-button>
        </div>
    </form>
</div>
