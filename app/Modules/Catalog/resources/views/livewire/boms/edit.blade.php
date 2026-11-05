<div>
    <x-page-header :title="'BOM · '.$item->name.' · version '.$bom->version" :description="$item->code">
        <x-status :status="$bom->is_active ? 'active' : $bom->status" />
        @if ($bom->isDraft())
            @can('boms.approve')
                <x-button wire:click="approve" wire:confirm="Approve version {{ $bom->version }}? It becomes the active BOM and can no longer be edited.">Approve</x-button>
            @endcan
            @can('boms.delete')
                <x-button variant="ghost" icon="trash" wire:click="deleteDraft" wire:confirm="Delete this draft?">Delete draft</x-button>
            @endcan
        @else
            @can('boms.create')
                <x-button variant="secondary" wire:click="newVersion">New version</x-button>
            @endcan
        @endif
        <x-button variant="ghost" :href="route('catalog.items.show', $item)">Item</x-button>
    </x-page-header>

    @unless ($bom->isDraft())
        <p class="mb-4 rounded-md bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-300">
            Approved {{ $bom->approved_at ? \App\Support\Format::datetime($bom->approved_at) : '' }}. Approved versions cannot change; create a new version to make changes.
        </p>
    @endunless

    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-date-input label="Effective date" wire:model="effective_date" required :disabled="! $editable" />
                <x-input label="Output quantity" type="number" step="any" min="0" wire:model="output_quantity" required :disabled="! $editable"
                         help="Components below are for this many {{ $item->stockUnit->symbol }}." />
                <x-input label="Notes" wire:model="notes" :disabled="! $editable" />
            </div>
        </x-card>

        <x-card title="Components" :padding="false">
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-900/60">
                        <tr>
                            <th class="px-3 py-2 text-left">Component</th>
                            <th class="px-3 py-2 text-left">Variant</th>
                            <th class="px-3 py-2 text-right">Quantity</th>
                            <th class="px-3 py-2 text-left">Unit</th>
                            <th class="px-3 py-2 text-right">Wastage %</th>
                            <th class="px-3 py-2 text-left">Issued at stage</th>
                            <th class="px-3 py-2 text-center">Optional</th>
                            <th class="px-3 py-2"><span class="sr-only">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($lines as $i => $line)
                            <tr wire:key="bl-{{ $i }}-{{ $line['id'] ?? 'new' }}" class="align-top">
                                <td class="px-3 py-2">
                                    <select wire:model.live="lines.{{ $i }}.component_item_id" @disabled(! $editable) aria-label="Component" class="w-64 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <option value="">Choose…</option>
                                        @foreach ($components as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                                    </select>
                                    @error("lines.$i.component_item_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-3 py-2">
                                    <select wire:model="lines.{{ $i }}.component_variant_id" @disabled(! $editable) aria-label="Variant" class="w-40 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <option value="">Any / by override</option>
                                        @foreach ($variantsByItem[(int) $line['component_item_id']] ?? [] as $id => $label) <option value="{{ $id }}">{{ $label }}</option> @endforeach
                                    </select>
                                    @error("lines.$i.component_variant_id") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" step="any" min="0" wire:model="lines.{{ $i }}.quantity" @disabled(! $editable) aria-label="Quantity" class="w-24 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                    @error("lines.$i.quantity") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="px-3 py-2">
                                    <select wire:model="lines.{{ $i }}.unit_id" @disabled(! $editable) aria-label="Unit" class="w-24 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                        @foreach ($units as $id => $symbol) <option value="{{ $id }}">{{ $symbol }}</option> @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model="lines.{{ $i }}.wastage_percent" @disabled(! $editable) aria-label="Wastage percent" class="w-20 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"></td>
                                <td class="px-3 py-2">
                                    <select wire:model="lines.{{ $i }}.stage_id" @disabled(! $editable) aria-label="Stage" class="w-36 rounded-md border-0 py-1.5 text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                        <option value="">—</option>
                                        @foreach ($stages as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                                    </select>
                                </td>
                                <td class="px-3 py-2 text-center"><input type="checkbox" wire:model="lines.{{ $i }}.is_optional" @disabled(! $editable) aria-label="Optional" class="size-4 rounded border-zinc-300 text-brand-600"></td>
                                <td class="px-3 py-2 text-right">
                                    @if ($editable)
                                        <button type="button" wire:click="removeLine({{ $i }})" class="p-1 text-zinc-400 hover:text-red-600" aria-label="Remove"><x-icon name="trash" class="size-4" /></button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-6 text-center text-sm text-zinc-500">No components yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error('lines') <p class="px-4 py-2 text-sm text-red-600">{{ $message }}</p> @enderror
            @if ($editable)
                <x-slot:footer>
                    <x-button variant="secondary" size="sm" wire:click="addLine" icon="plus" class="mr-auto">Add component</x-button>
                    <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
                </x-slot:footer>
            @endif
        </x-card>
    </form>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-card title="Variant overrides" description="Changes for some variants only, e.g. more leather for Large or red thread for Red.">
            @if ($editable)
                <x-slot:actions><x-button size="sm" variant="secondary" wire:click="openOverride" icon="plus">Add</x-button></x-slot:actions>
            @endif
            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @forelse ($overrides as $o)
                    <li class="flex items-start justify-between gap-3 py-2" wire:key="o-{{ $o->id }}">
                        <span>
                            <span class="font-medium">{{ $o->matchVariant ? 'Only '.$o->matchVariant->label : 'All '.$o->matchAttributeValue?->attribute->name.' = '.$o->matchAttributeValue?->value }}:</span>
                            @if ($o->removes_line)
                                remove {{ $o->line?->componentItem->code }}
                            @elseif ($o->line)
                                {{ $o->line->componentItem->code }} →
                                {{ $o->componentItem ? $o->componentItem->code : '' }}{{ $o->componentVariant ? ' '.$o->componentVariant->label : '' }}
                                {{ $o->quantity !== null ? 'qty '.\App\Support\Format::qty($o->quantity) : '' }}
                                {{ $o->wastage_percent !== null ? 'wastage '.$o->wastage_percent.'%' : '' }}
                            @else
                                add {{ $o->componentItem?->code }}{{ $o->componentVariant ? ' '.$o->componentVariant->label : '' }} × @qty($o->quantity)
                            @endif
                        </span>
                        @if ($editable)
                            <button type="button" wire:click="removeOverride({{ $o->id }})" class="text-xs text-zinc-500 hover:text-red-600">Remove</button>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-zinc-500">No overrides.</li>
                @endforelse
            </ul>
        </x-card>

        <x-card title="Requirement preview" description="Required = quantity × component quantity ÷ output quantity × (1 + wastage %). Multi-level BOMs are expanded.">
            <form wire:submit="explode" class="flex flex-wrap items-end gap-3">
                <x-select label="Variant" wire:model="preview_variant_id" :options="$parentVariants" />
                <x-input label="Quantity" type="number" step="any" min="0" wire:model="preview_quantity" class="w-28" />
                <x-button type="submit" variant="secondary">Calculate</x-button>
            </form>
            @if ($previewError)
                <p class="mt-3 text-sm text-red-600">{{ $previewError }}</p>
            @endif
            @if ($preview)
                <table class="mt-4 min-w-full text-sm">
                    <thead class="text-xs uppercase text-zinc-500"><tr><th class="py-1 text-left">Component</th><th class="py-1 text-right">Required</th><th class="py-1 text-left pl-2">Stage</th></tr></thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($preview as $row)
                            <tr>
                                <td class="py-1" style="padding-left: {{ ($row['level'] - 1) * 1.25 }}rem">
                                    {{ $row['label'] }}
                                    @if ($row['wastage'] > 0) <span class="text-xs text-zinc-500">(+@qty($row['wastage'])%)</span> @endif
                                    @if ($row['optional']) <x-badge>optional</x-badge> @endif
                                    @if ($row['has_bom']) <x-badge color="blue">made</x-badge> @endif
                                </td>
                                <td class="py-1 text-right tabular">@qty($row['quantity']) {{ $row['unit'] }}</td>
                                <td class="py-1 pl-2 text-zinc-500">{{ $row['stage'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>

    <x-modal wire:model="showOverride" title="Add override" max-width="xl">
        <form wire:submit="saveOverride" id="override-form" class="grid gap-4">
            <x-select label="Applies to" wire:model="match" :options="$matchOptions" placeholder="Choose…" required help="Variant-specific overrides win over attribute overrides." />
            <x-select label="Change" wire:model.live="override_line_id" :options="$savedLines->mapWithKeys(fn ($l) => [$l->id => 'Line: '.$l->componentItem->code.' '.$l->componentItem->name])->all()" placeholder="Add an extra component" />
            @if ($override_line_id)
                <x-checkbox wire:model.live="override_removes" label="Remove this component for these variants" />
            @endif
            @unless ($override_removes)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-select label="{{ $override_line_id ? 'Replace component with' : 'Component' }}" wire:model.live="override_item_id" :options="$components" :placeholder="$override_line_id ? 'Keep component' : 'Choose…'" />
                    <x-select label="Variant" wire:model="override_variant_id" :options="$variantsByItem[(int) $override_item_id] ?? []" placeholder="—" />
                    <x-input label="Quantity" type="number" step="any" min="0" wire:model="override_quantity" :help="$override_line_id ? 'Empty = keep the line quantity.' : null" />
                    <x-input label="Wastage %" type="number" step="any" min="0" wire:model="override_wastage" :help="$override_line_id ? 'Empty = keep.' : null" />
                </div>
            @endunless
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="override-form">Add override</x-button>
        </x-slot:footer>
    </x-modal>
</div>
