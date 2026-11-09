<div>
    <div class="flex items-center justify-between border-b border-line px-4 py-2">
        <p class="text-xs text-subtle">Every item has at least one variant. Generate combinations from attributes, then remove the ones you don't sell or stock.</p>
        @if ($canEdit)
            <x-button size="sm" variant="secondary" wire:click="openGenerator">Attributes &amp; combinations</x-button>
        @endif
    </div>

    <x-table>
        <x-slot:head>
            <x-table.th>SKU</x-table.th>
            <x-table.th>Variant</x-table.th>
            <x-table.th>Barcode</x-table.th>
            <x-table.th align="right">Price</x-table.th>
            @if ($canSeeCost) <x-table.th align="right">Cost</x-table.th> @endif
            <x-table.th align="right">Min stock</x-table.th>
            <x-table.th>Status</x-table.th>
            <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
        </x-slot:head>
        @foreach ($variants as $v)
            <tr wire:key="v-{{ $v->id }}">
                <x-table.td class="font-mono">{{ $v->sku }}</x-table.td>
                <x-table.td>{{ $v->label !== '' ? $v->label : 'Standard' }}</x-table.td>
                <x-table.td class="font-mono text-xs">{{ $v->barcode }}</x-table.td>
                <x-table.td align="right">{{ \App\Support\Format::money($v->price_override, null, 4) ?: '—' }}</x-table.td>
                @if ($canSeeCost) <x-table.td align="right">{{ \App\Support\Format::money($v->cost_override, null, 4) ?: '—' }}</x-table.td> @endif
                <x-table.td align="right">{{ \App\Support\Format::qty($v->min_stock) ?: '—' }}</x-table.td>
                <x-table.td><x-status :status="$v->is_active ? 'active' : 'inactive'" /></x-table.td>
                <x-table.td align="right" class="whitespace-nowrap">
                    @if ($canEdit)
                        <x-button variant="ghost" size="sm" wire:click="edit({{ $v->id }})">Edit</x-button>
                        <x-button variant="ghost" size="sm" wire:click="remove({{ $v->id }})" wire:confirm="Remove variant {{ $v->sku }}?">Remove</x-button>
                    @endif
                </x-table.td>
            </tr>
        @endforeach
    </x-table>

    <x-modal wire:model="showGenerator" title="Attributes and combinations" max-width="2xl">
        @if ($attributeList->isEmpty())
            <p class="text-sm text-subtle">No attributes yet. Create them in Catalog › Attributes (e.g. Color: Red, Blue; Size: S, M, L).</p>
        @else
            <p class="mb-4 text-sm text-muted">Tick the values this item comes in. Every combination becomes a variant; existing variants are kept.</p>
            <div class="space-y-4">
                @foreach ($attributeList as $attribute)
                    <fieldset wire:key="attr-{{ $attribute->id }}">
                        <legend class="mb-1 text-sm font-medium">{{ $attribute->name }}</legend>
                        <div class="flex flex-wrap gap-x-4 gap-y-2">
                            @foreach ($attribute->values as $value)
                                <x-checkbox wire:model="selected.{{ $attribute->id }}" value="{{ $value->id }}" :label="$value->value" wire:key="val-{{ $value->id }}" />
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        @endif
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button wire:click="generate" :disabled="$attributeList->isEmpty()">Generate variants</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showEdit" title="Edit variant">
        <form wire:submit="save" id="variant-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="SKU" wire:model="sku" required />
            <x-input label="Barcode" wire:model="barcode" />
            <x-input label="Price override" type="number" step="any" min="0" wire:model="price_override" help="Empty = item default." />
            @if ($canSeeCost)
                <x-input label="Cost override (PKR)" type="number" step="any" min="0" wire:model="cost_override" help="Empty = item default." />
            @endif
            <x-input label="Minimum stock" type="number" step="any" min="0" wire:model="min_stock" />
            <x-checkbox wire:model="is_active" label="Active" class="sm:col-span-2" />
            @error('is_active') <p class="text-xs text-danger sm:col-span-2">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="variant-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
