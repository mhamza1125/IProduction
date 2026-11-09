@php($inv = $invoice)
<div>
    <x-page-header :title="'Invoice '.$inv->number" :description="$inv->customer->name.' · '.\App\Support\Format::date($inv->invoice_date).($inv->due_date ? ' · due '.\App\Support\Format::date($inv->due_date) : '')" />
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <x-document-actions :document="$inv" :edit-route="route('delivery.invoices.edit', $inv)" :print-route="$inv->isDraft() ? null : route('delivery.invoices.print', $inv)" />
        @if ($inv->isDraft()) @can('invoices.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan @endif
        @if ($inv->isPosted()) @can('credit_notes.create') <x-button variant="secondary" :href="route('delivery.credit-notes.create', ['invoice' => $inv->id])">Credit note</x-button> @endcan @endif
    </div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Lines" :padding="false">
                <x-table>
                    <x-slot:head><x-table.th>Item</x-table.th><x-table.th>Delivery · order</x-table.th><x-table.th align="right">Qty</x-table.th><x-table.th align="right">Price</x-table.th><x-table.th align="right">Discount</x-table.th><x-table.th align="right">Tax</x-table.th><x-table.th align="right">Amount</x-table.th></x-slot:head>
                    @foreach ($inv->lines as $l)
                        <tr wire:key="il-{{ $l->id }}">
                            <x-table.td>{{ $l->variant->fullLabel() }}@if ($l->description)<div class="text-xs text-subtle">{{ $l->description }}</div>@endif</x-table.td>
                            <x-table.td class="font-mono text-xs">{{ $l->deliveryLine->delivery->number }} · {{ $l->orderLine->order->number }}</x-table.td>
                            <x-table.td align="right">@qty($l->quantity)</x-table.td>
                            <x-table.td align="right">@money($l->unit_price, null, 4)@if (\App\Support\Decimal::compare($l->unit_price, $l->order_price) !== 0)<div class="text-xs text-warning">order @money($l->order_price, null, 4)</div>@endif</x-table.td>
                            <x-table.td align="right">@if ($l->discount)@money($l->discount)@endif</x-table.td>
                            <x-table.td align="right">@if ($l->tax_rate !== null){{ (float) $l->tax_rate }}% · @money($l->tax_amount)@else <span class="text-subtle">none</span>@endif</x-table.td>
                            <x-table.td align="right">@money($l->amount)</x-table.td>
                        </tr>
                    @endforeach
                    <tr><x-table.td colspan="6" align="right">Subtotal</x-table.td><x-table.td align="right">@money($inv->subtotal)</x-table.td></tr>
                    @if ((float) $inv->discount_total)<tr><x-table.td colspan="6" align="right">Discount</x-table.td><x-table.td align="right">−@money($inv->discount_total)</x-table.td></tr>@endif
                    @if ((float) $inv->tax_total)<tr><x-table.td colspan="6" align="right">Tax</x-table.td><x-table.td align="right">@money($inv->tax_total)</x-table.td></tr>@endif
                    <tr class="font-semibold"><x-table.td colspan="6" align="right">Total</x-table.td><x-table.td align="right">@money($inv->total, $inv->currency_code)</x-table.td></tr>
                </x-table>
            </x-card>
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$inv" :key="'a-'.$inv->id" /></x-card>
        </div>
        <div class="space-y-6">
            @if ($inv->isPosted())
                <x-card title="Balance">
                    <p class="text-2xl font-semibold tabular">@money($inv->balance(), $inv->currency_code)</p>
                    <ul class="mt-3 space-y-1 text-sm">
                        @forelse ($inv->allocations as $a)<li>{{ $a->source instanceof \App\Modules\Delivery\Models\CreditNote ? 'Credit note' : 'Receipt' }} {{ $a->source?->number }} · @date($a->allocation_date) · @money($a->amount)</li>@empty<li class="text-subtle">Nothing received or credited yet.</li>@endforelse
                    </ul>
                    @if ($credits->isNotEmpty() && (float) $inv->balance() > 0)
                        @can('credit_notes.post')
                            <form wire:submit="applyCredit" class="mt-4 grid gap-2 border-t border-line pt-3">
                                <x-select label="Apply customer credit" wire:model="credit_note_id" :options="$credits" placeholder="Choose…" />
                                <x-input label="Amount" type="number" step="any" min="0" wire:model="credit_amount" />
                                <x-button type="submit" size="sm" variant="secondary">Apply</x-button>
                            </form>
                        @endcan
                    @endif
                </x-card>
            @endif
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Customer"><a href="{{ route('people.customers.show', $inv->customer_id) }}" wire:navigate class="text-link hover:underline">{{ $inv->customer->label() }}</a></x-people::detail>
                    <x-people::detail label="Currency" :value="$inv->currency_code" />
                    <x-people::detail label="Payment terms" :value="$inv->payment_terms" />
                    <x-people::detail label="Notes" :value="$inv->notes" />
                    @if ($inv->cancel_reason) <x-people::detail label="Cancel reason" :value="$inv->cancel_reason" /> @endif
                </dl>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$inv" :key="'n-'.$inv->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$inv" :key="'h-'.$inv->id" /></x-card>
        </div>
    </div>
</div>
