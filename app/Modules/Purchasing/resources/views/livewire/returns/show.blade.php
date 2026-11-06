<div>
    <x-page-header :title="'Purchase return '.$return->number" :description="$return->vendor->name.' · '.\App\Support\Format::date($return->return_date).' · '.$return->reason">
        @if ($return->isDraft())
            @can('purchase_returns.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan
        @endif
    </x-page-header>
    <div class="mb-6"><x-document-actions :document="$return" :print-route="route('purchasing.returns.print', $return)" /></div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card :padding="false" title="Lines">
                <x-table>
                    <x-slot:head><x-table.th>Item</x-table.th><x-table.th>Lot</x-table.th><x-table.th>From</x-table.th><x-table.th align="right">Qty</x-table.th><x-table.th align="right">Rate</x-table.th><x-table.th align="right">Amount</x-table.th></x-slot:head>
                    @foreach ($lines as $l)
                        <tr><x-table.td>{{ $l->variant->fullLabel() }}</x-table.td><x-table.td class="font-mono text-xs">{{ $l->lot->lot_number }}</x-table.td><x-table.td>{{ \Illuminate\Support\Str::headline($l->status) }} · {{ $l->location->name }}</x-table.td><x-table.td align="right">@qty($l->quantity) {{ $l->variant->item->stockUnit->symbol }}</x-table.td><x-table.td align="right">@money($l->rate, null, 4)</x-table.td><x-table.td align="right">@money($l->amount)</x-table.td></tr>
                    @endforeach
                    <tr class="font-semibold"><x-table.td colspan="5">Total PKR</x-table.td><x-table.td align="right">@money($return->total)</x-table.td></tr>
                </x-table>
            </x-card>
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$return" :key="'a-'.$return->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Goods receipt"><a href="{{ route('purchasing.receipts.show', $return->goods_receipt_id) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $return->receipt->number }}</a></x-people::detail>
                    <x-people::detail label="Replacement required" :value="$return->replacement_required ? 'Yes' : 'No'" />
                    <x-people::detail label="Notes" :value="$return->notes" />
                </dl>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$return" :key="'n-'.$return->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$return" :key="'h-'.$return->id" /></x-card>
        </div>
    </div>
</div>
