<div>
    <x-page-header :title="$voucher ? 'Edit '.$voucher->number : 'New journal voucher'" description="A manual debit and credit entry. It must balance. Tag a party to put the line on their ledger; a debit to an employee can be marked to deduct from payroll." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-date-input label="Date" wire:model="voucher_date" required />
                <x-select label="Currency" wire:model.live="currency_code" :options="$currencies" />
                <x-input label="Description" wire:model="description" required class="lg:col-span-2" />
            </div>
        </x-card>
        <x-card title="Lines" :padding="false">
            <x-table>
                <x-slot:head><x-table.th>Account</x-table.th><x-table.th>Party</x-table.th><x-table.th align="right">Debit</x-table.th><x-table.th align="right">Credit</x-table.th><x-table.th>Memo</x-table.th><x-table.th>Payroll</x-table.th><x-table.th></x-table.th></x-slot:head>
                @foreach ($lines as $i => $l)
                    <tr wire:key="jl-{{ $i }}">
                        <x-table.td><x-select wire:model="lines.{{ $i }}.account" :options="$accounts" placeholder="Choose…" aria-label="Account" /></x-table.td>
                        <x-table.td><x-select wire:model="lines.{{ $i }}.party" :options="$parties" placeholder="—" aria-label="Party" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model.blur="lines.{{ $i }}.debit" aria-label="Debit" /></x-table.td>
                        <x-table.td><x-input type="number" step="any" min="0" wire:model.blur="lines.{{ $i }}.credit" aria-label="Credit" /></x-table.td>
                        <x-table.td><x-input wire:model="lines.{{ $i }}.memo" aria-label="Memo" /></x-table.td>
                        <x-table.td><input type="checkbox" wire:model="lines.{{ $i }}.deduct_from_payroll" aria-label="Deduct from payroll" class="size-4 rounded border-zinc-300 text-brand-600"></x-table.td>
                        <x-table.td><x-button size="sm" variant="ghost" icon="trash" wire:click="removeLine({{ $i }})" aria-label="Remove line" /></x-table.td>
                    </tr>
                @endforeach
                <tr @class(['font-semibold', 'text-red-600' => \App\Support\Decimal::compare($debits, $credits) !== 0])><x-table.td colspan="2">Totals</x-table.td><x-table.td align="right">@money($debits)</x-table.td><x-table.td align="right">@money($credits)</x-table.td><x-table.td colspan="3">{{ \App\Support\Decimal::compare($debits, $credits) !== 0 ? 'Not balanced' : '' }}</x-table.td></tr>
            </x-table>
            <div class="border-t border-zinc-200 p-3 dark:border-zinc-800"><x-button size="sm" variant="secondary" icon="plus" wire:click="addLine">Add line</x-button></div>
            @error('lines') <p class="border-t border-zinc-200 px-4 py-3 text-sm text-red-600 dark:border-zinc-800">{{ $message }}</p> @enderror
        </x-card>
        <div class="flex justify-end gap-2">
            <x-button variant="ghost" :href="$voucher ? route('finance.vouchers.show', $voucher) : route('finance.vouchers.index')">Cancel</x-button>
            <x-button type="submit">Save draft</x-button>
        </div>
    </form>
</div>
