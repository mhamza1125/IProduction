<div>
    <x-page-header :title="'Vendor bill '.$bill->number" :description="$bill->vendor->name.' · invoice '.$bill->vendor_invoice_number.' · '.\App\Support\Format::date($bill->bill_date)">
        @if ($bill->isDraft())
            @can('vendor_bills.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan
        @endif
        @if ($bill->isPosted() && (float) $bill->balance() > 0)
            @can('payments.create') <x-button :href="route('finance.payments.create', ['kind' => 'vendor', 'payee' => $bill->vendor_id])" icon="banknotes">Pay</x-button> @endcan
        @endif
    </x-page-header>
    <div class="mb-6"><x-document-actions :document="$bill" :edit-route="route('purchasing.bills.edit', $bill)" /></div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card :padding="false" title="Lines">
                <x-table>
                    <x-slot:head><x-table.th>Receipt</x-table.th><x-table.th>Item</x-table.th><x-table.th align="right">Qty</x-table.th><x-table.th align="right">Rate</x-table.th><x-table.th align="right">Amount</x-table.th></x-slot:head>
                    @foreach ($lines as $l)
                        <tr><x-table.td class="font-mono text-xs">{{ $l->receiptLine->receipt->number }}</x-table.td><x-table.td>{{ $l->variant->fullLabel() }}</x-table.td><x-table.td align="right">@qty($l->quantity)</x-table.td><x-table.td align="right">@money($l->rate, null, 4)</x-table.td><x-table.td align="right">@money($l->amount)</x-table.td></tr>
                    @endforeach
                    <tr><x-table.td colspan="4">Goods</x-table.td><x-table.td align="right">@money($bill->goods_amount)</x-table.td></tr>
                    <tr><x-table.td colspan="4">Tax</x-table.td><x-table.td align="right">@money($bill->tax_amount)</x-table.td></tr>
                    <tr><x-table.td colspan="4">Charges</x-table.td><x-table.td align="right">@money($bill->charges_amount)</x-table.td></tr>
                    <tr><x-table.td colspan="4">Deductions</x-table.td><x-table.td align="right">−@money($bill->deductions_amount)</x-table.td></tr>
                    <tr class="font-semibold"><x-table.td colspan="4">Total PKR</x-table.td><x-table.td align="right">@money($bill->total)</x-table.td></tr>
                    <tr><x-table.td colspan="4">Paid</x-table.td><x-table.td align="right">@money($bill->paid_amount)</x-table.td></tr>
                    <tr class="font-semibold"><x-table.td colspan="4">Balance</x-table.td><x-table.td align="right">@money($bill->balance())</x-table.td></tr>
                </x-table>
            </x-card>
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$bill" :key="'a-'.$bill->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Payments">
                <ul class="space-y-1 text-sm">
                    @forelse ($payments as $p)
                        <li class="flex justify-between"><a href="{{ route('finance.payments.show', $p->payment_id) }}" wire:navigate class="font-mono text-link hover:underline">{{ $p->payment->number }}</a><span>@money($p->amount)</span></li>
                    @empty
                        <li class="text-subtle">No payments yet.</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$bill" :key="'n-'.$bill->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$bill" :key="'h-'.$bill->id" /></x-card>
        </div>
    </div>
</div>
