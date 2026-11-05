<div>
    <x-page-header :title="$adjustment ? 'Edit '.$adjustment->number : 'New stock adjustment'" description="Positive quantity adds stock (a new Adjustment lot unless you pick a lot). Negative removes it (the chosen lot, else oldest first)." />

    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card class="max-w-4xl">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-date-input label="Date" wire:model="adjustment_date" required />
                <x-select label="Reason" wire:model="reason_id" :options="$reasons" placeholder="Choose…" required />
                <x-input label="Notes" wire:model="notes" />
            </div>
        </x-card>

        <x-card title="Lines" :padding="false">
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left">Item</th><th class="px-3 py-2 text-left">Location</th><th class="px-3 py-2 text-left">Stage</th>
                            <th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Lot</th><th class="px-3 py-2 text-right">Qty (±)</th>
                            @if ($canSeeCost) <th class="px-3 py-2 text-right">Unit cost</th> @endif
                            <th class="px-3 py-2"><span class="sr-only">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($lines as $i => $line)
                            <tr wire:key="al-{{ $i }}" class="align-top">
                                <td class="px-3 py-2">
                                    <select wire:model.live="lines.{{ $i }}.item_variant_id" aria-label="Item" class="w-72 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <option value="">Choose…</option>
                                        @foreach ($variants as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                                    </select>
                                    @error("lines.$i.item_variant_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-3 py-2"><select wire:model="lines.{{ $i }}.location_id" aria-label="Location" class="w-36 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">@foreach ($locations as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></td>
                                <td class="px-3 py-2"><select wire:model="lines.{{ $i }}.stage_id" aria-label="Stage" class="w-32 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"><option value="">—</option>@foreach ($stages as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></td>
                                <td class="px-3 py-2"><select wire:model="lines.{{ $i }}.status" aria-label="Status" class="w-32 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">@foreach ($statuses as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></td>
                                <td class="px-3 py-2">
                                    <select wire:model="lines.{{ $i }}.lot_id" aria-label="Lot" class="w-36 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <option value="">New lot / oldest first</option>
                                        @foreach ($lotsByVariant[(int) $line['item_variant_id']] ?? [] as $id => $number)<option value="{{ $id }}">{{ $number }}</option>@endforeach
                                    </select>
                                    @error("lines.$i.lot_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" step="any" wire:model="lines.{{ $i }}.quantity" aria-label="Quantity" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                    @error("lines.$i.quantity") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                @if ($canSeeCost)
                                    <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model="lines.{{ $i }}.unit_cost" aria-label="Unit cost" placeholder="item default" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"></td>
                                @endif
                                <td class="px-3 py-2 text-right"><button type="button" wire:click="removeLine({{ $i }})" class="p-1 text-zinc-400 hover:text-red-600" aria-label="Remove"><x-icon name="trash" class="size-4" /></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('lines') <p class="px-4 py-2 text-xs text-red-600">{{ $message }}</p> @enderror
            <x-slot:footer>
                <x-button variant="secondary" size="sm" wire:click="addLine" icon="plus" class="mr-auto">Add line</x-button>
                <x-button variant="secondary" :href="$adjustment ? route('inventory.adjustments.show', $adjustment) : route('inventory.adjustments.index')">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Save draft</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
