<div>
    <x-page-header :title="$receipt ? 'Edit '.$receipt->number : 'Receive goods'" :description="$order->number.' · '.$order->vendor->name" />

    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card class="max-w-4xl">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-date-input label="Received date" wire:model="receipt_date" required />
                <x-input label="Vendor delivery note no." wire:model="vendor_delivery_note" />
                <x-input label="Notes" wire:model="notes" />
            </div>
        </x-card>

        <x-card title="What arrived" description="Received = accepted + rejected. Leave received empty for lines that did not arrive." :padding="false">
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-sunken text-xs uppercase text-subtle">
                        <tr>
                            <th class="px-3 py-2 text-left">Item</th><th class="px-3 py-2 text-right">Outstanding</th>
                            <th class="px-3 py-2 text-right">Received</th><th class="px-3 py-2 text-right">Rejected</th><th class="px-3 py-2 text-right">Accepted</th>
                            <th class="px-3 py-2 text-left">Rejected goods</th><th class="px-3 py-2 text-left">Into</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($order->lines as $pl)
                            @php($l = $lines[$pl->id] ?? null)
                            @continue($l === null)
                            @php($over = is_numeric($l['accepted_qty']) && \App\Support\Decimal::compare($l['accepted_qty'], $limits[$pl->id]['allowed']) > 0)
                            <tr wire:key="gl-{{ $pl->id }}" class="align-top">
                                <td class="px-3 py-2">{{ $pl->variant->fullLabel() }}<div class="text-xs text-subtle">{{ $pl->unit->symbol }} · ordered @qty($pl->quantity)</div></td>
                                <td class="px-3 py-2 text-right tabular">@qty($limits[$pl->id]['outstanding'])</td>
                                <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model.blur="lines.{{ $pl->id }}.received_qty" aria-label="Received" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></td>
                                <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model.blur="lines.{{ $pl->id }}.rejected_qty" aria-label="Rejected" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></td>
                                <td class="px-3 py-2">
                                    <input type="number" step="any" min="0" wire:model.blur="lines.{{ $pl->id }}.accepted_qty" aria-label="Accepted" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface">
                                    @error("lines.{$pl->id}.accepted_qty") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                                    @if ($over)
                                        <div class="mt-1 rounded bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-500/10 dark:text-amber-300">
                                            Above tolerance (max @qty($limits[$pl->id]['allowed'])). An authorised user must choose:
                                            <select wire:model="lines.{{ $pl->id }}.over_receipt_action" aria-label="Extra quantity decision" class="mt-1 block w-full rounded border-0 py-1 text-xs ring-1 ring-amber-300 bg-surface">
                                                <option value="">Choose…</option>
                                                @foreach ($overActions as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    @if (is_numeric($l['rejected_qty']) && (float) $l['rejected_qty'] > 0)
                                        <select wire:model="lines.{{ $pl->id }}.rejected_action" aria-label="Rejected goods" class="w-40 rounded-md border-0 py-1.5 text-xs ring-1 ring-line-strong bg-surface">
                                            <option value="return">Return to vendor</option><option value="keep">Keep (Rejected status, we pay)</option>
                                        </select>
                                        <select wire:model="lines.{{ $pl->id }}.reject_reason_id" aria-label="Reject reason" class="mt-1 w-40 rounded-md border-0 py-1.5 text-xs ring-1 ring-line-strong bg-surface">
                                            <option value="">Reason…</option>@foreach ($reasons as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach
                                        </select>
                                        @error("lines.{$pl->id}.reject_reason_id") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                                    @else
                                        <span class="text-xs text-subtle">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <select wire:model="lines.{{ $pl->id }}.location_id" aria-label="Location" class="w-36 rounded-md border-0 py-1.5 text-sm ring-1 ring-line-strong bg-surface">@foreach ($locations as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
                                    <select wire:model="lines.{{ $pl->id }}.stage_id" aria-label="Stage" class="mt-1 w-36 rounded-md border-0 py-1.5 text-xs ring-1 ring-line-strong bg-surface"><option value="">No stage (store)</option>@foreach ($stages as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('lines') <p class="px-4 py-2 text-sm text-danger">{{ $message }}</p> @enderror
            <x-slot:footer>
                <x-button variant="secondary" :href="route('purchasing.orders.show', $order)">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Save draft</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
