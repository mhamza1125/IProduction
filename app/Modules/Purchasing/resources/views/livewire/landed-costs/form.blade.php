<div>
    <x-page-header :title="$landedCost ? 'Edit '.$landedCost->number : 'New landed cost'"
                   description="Freight, delivery, customs duty, clearing and other charges paid for goods you received. They are added to those goods' cost (and to what production made from them), not expensed." />
    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-date-input label="Date" wire:model="cost_date" required />
                <x-select label="Payment" wire:model.live="payment" :options="['payable' => 'Payable to a vendor (transporter, agent)', 'now' => 'Paid now']" />
                @if ($payment === 'now')
                    <x-select label="Paid from" wire:model="money_account_id" :options="$accounts" placeholder="Choose…" required />
                @else
                    <x-select label="Payable to" wire:model="vendor_id" :options="$vendors" placeholder="Choose…" required />
                @endif
                <x-select label="Spread over the goods" wire:model="method" :options="\App\Modules\Purchasing\Models\LandedCost::METHODS"
                          help="By value: each line in proportion to its cost. By quantity: per unit received (use when the goods are similar)." />
                <x-input label="Reference (bill, bilty, GD number)" wire:model="reference" />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
            </div>
        </x-card>

        <x-card title="Charges" :padding="false">
            <table class="min-w-full text-sm">
                <thead class="bg-sunken text-left text-xs font-medium uppercase tracking-wide text-subtle">
                    <tr><th class="px-3 py-2">Charge</th><th class="px-3 py-2">Description</th><th class="px-3 py-2 text-right">Amount (PKR)</th><th class="px-3 py-2"><span class="sr-only">Remove</span></th></tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($charges as $i => $c)
                        <tr wire:key="c-{{ $i }}">
                            <td class="px-3 py-2"><select wire:model="charges.{{ $i }}.charge_type" aria-label="Charge" class="rounded-md border-0 bg-surface py-1.5 text-sm ring-1 ring-line-strong">@foreach ($types as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></td>
                            <td class="px-3 py-2"><input wire:model="charges.{{ $i }}.description" aria-label="Description" class="w-full rounded-md border-0 bg-surface py-1.5 text-sm ring-1 ring-line-strong"></td>
                            <td class="px-3 py-2"><input type="number" step="0.01" min="0" wire:model="charges.{{ $i }}.amount" aria-label="Amount" class="w-32 rounded-md border-0 bg-surface py-1.5 text-right text-sm ring-1 ring-line-strong">
                                @error("charges.$i.amount")<div class="text-xs text-danger">{{ $message }}</div>@enderror</td>
                            <td class="px-3 py-2 text-right">@if (count($charges) > 1)<x-button size="sm" variant="ghost" wire:click="removeCharge({{ $i }})">Remove</x-button>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="border-t border-line px-3 py-2"><x-button size="sm" variant="secondary" wire:click="addCharge" icon="plus">Add charge</x-button></div>
        </x-card>

        <x-card title="Goods receipts these charges are for">
            <input wire:model.live.debounce.300ms="receiptSearch" placeholder="Search receipt number…" aria-label="Search receipts" class="mb-3 w-full max-w-xs rounded-md border-0 bg-surface py-2 text-sm ring-1 ring-line-strong">
            <div class="grid max-h-72 gap-1 overflow-y-auto">
                @forelse ($receipts as $r)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="{{ $r['id'] }}" wire:model="receipt_ids" class="rounded ring-line-strong"> <span class="font-mono">{{ $r['label'] }}</span></label>
                @empty
                    <p class="text-sm text-subtle">No posted goods receipts in the last six months.</p>
                @endforelse
            </div>
            @error('receipt_ids')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button variant="ghost" :href="$landedCost ? route('purchasing.landed-costs.show', $landedCost) : route('purchasing.landed-costs.index')">Cancel</x-button>
            <x-button type="submit">Save draft</x-button>
        </div>
    </form>
</div>
