<div>
    <x-page-header title="Return goods to vendor" :description="$receipt->number.' · '.$receipt->vendor->name" />
    <form wire:submit="save" class="space-y-6">
        <x-card class="max-w-4xl">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-date-input label="Return date" wire:model="return_date" required />
                <x-input label="Reason" wire:model="reason" required class="sm:col-span-2" />
                <x-input label="Notes" wire:model="notes" class="sm:col-span-2" />
                <x-checkbox wire:model="replacement_required" label="Replacement required" help="The returned quantity becomes outstanding on the purchase order again." />
            </div>
        </x-card>
        <x-card title="Stock from this receipt" :padding="false">
            <x-table empty="Nothing from this receipt is still in stock.">
                <x-slot:head><x-table.th>Item</x-table.th><x-table.th>Lot</x-table.th><x-table.th>Status · location</x-table.th><x-table.th align="right">In stock</x-table.th><x-table.th align="right">Return</x-table.th></x-slot:head>
                @foreach ($buckets as $b)
                    <tr wire:key="rb-{{ $b->id }}">
                        <x-table.td>{{ $b->variant->fullLabel() }}</x-table.td>
                        <x-table.td class="font-mono text-xs">{{ $b->lot->lot_number }}</x-table.td>
                        <x-table.td>{{ \Illuminate\Support\Str::headline($b->status) }} · {{ $b->location->name }}</x-table.td>
                        <x-table.td align="right">@qty($b->quantity) {{ $b->variant->item->stockUnit->symbol }}</x-table.td>
                        <x-table.td align="right"><input type="number" step="any" min="0" wire:model="quantities.{{ $b->id }}" aria-label="Quantity to return" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface">@error("quantities.{$b->id}") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror</x-table.td>
                    </tr>
                @endforeach
            </x-table>
            @error('quantities') <p class="px-4 py-2 text-sm text-danger">{{ $message }}</p> @enderror
            <x-slot:footer>
                <x-button variant="secondary" :href="route('purchasing.receipts.show', $receipt)">Cancel</x-button>
                <x-button type="submit">Save draft</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
