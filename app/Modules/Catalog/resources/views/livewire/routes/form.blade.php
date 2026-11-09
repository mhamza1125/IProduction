<div>
    <x-page-header :title="$productionRoute ? 'Route: '.$productionRoute->name : 'New route'" description="PTCs copy the route when they are created; changes here only affect new PTCs.">
        @if ($productionRoute)
            @can('routes.delete')
                <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete this route?">Delete</x-button>
            @endcan
        @endif
    </x-page-header>

    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card class="max-w-4xl">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select label="Item" wire:model="item_id" :options="$items" placeholder="Choose an item that can be made…" required :disabled="(bool) $productionRoute" />
                <x-input label="Route name" wire:model="name" required />
                <x-checkbox wire:model="is_default" label="Default route for this item" />
                <x-checkbox wire:model="is_active" label="Active" />
                <x-textarea label="Notes" wire:model="notes" rows="2" class="sm:col-span-2" />
            </div>
        </x-card>

        <x-card title="Stages" :padding="false">
            <div class="relative overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-sunken text-xs uppercase text-subtle">
                        <tr>
                            <th class="px-3 py-2 text-left">#</th>
                            <th class="px-3 py-2 text-left">Stage</th>
                            <th class="px-3 py-2 text-left">Done by</th>
                            @if ($canSeeCost)
                                <th class="px-3 py-2 text-right">Wage rate</th>
                                <th class="px-3 py-2 text-right">Contractor rate</th>
                            @endif
                            <th class="px-3 py-2 text-left">Machine</th>
                            <th class="px-3 py-2 text-center">Optional</th>
                            <th class="px-3 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($lines as $i => $line)
                            <tr wire:key="line-{{ $i }}" class="align-top">
                                <td class="px-3 py-2 text-subtle">{{ $i + 1 }}</td>
                                <td class="px-3 py-2">
                                    <select wire:model="lines.{{ $i }}.stage_id" aria-label="Stage" class="w-44 rounded-md border-0 py-1.5 text-sm ring-1 ring-line-strong bg-surface">
                                        <option value="">Choose…</option>
                                        @foreach ($stages as $id => $name) <option value="{{ $id }}">{{ $name }}</option> @endforeach
                                    </select>
                                    @error("lines.$i.stage_id") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                                    <input type="text" wire:model="lines.{{ $i }}.instructions" placeholder="Instructions" aria-label="Instructions" class="mt-1 block w-44 rounded-md border-0 py-1 text-xs ring-1 ring-line bg-surface">
                                </td>
                                <td class="px-3 py-2">
                                    <select wire:model="lines.{{ $i }}.default_work_type" aria-label="Done by" class="rounded-md border-0 py-1.5 text-sm ring-1 ring-line-strong bg-surface">
                                        @foreach ($workTypes as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                                    </select>
                                </td>
                                @if ($canSeeCost)
                                    <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model="lines.{{ $i }}.wage_rate" aria-label="Wage rate" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></td>
                                    <td class="px-3 py-2"><input type="number" step="any" min="0" wire:model="lines.{{ $i }}.contractor_rate" aria-label="Contractor rate" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></td>
                                @endif
                                <td class="px-3 py-2"><input type="text" wire:model="lines.{{ $i }}.machine" aria-label="Machine" class="w-32 rounded-md border-0 py-1.5 text-sm ring-1 ring-line-strong bg-surface"></td>
                                <td class="px-3 py-2 text-center"><input type="checkbox" wire:model="lines.{{ $i }}.is_optional" aria-label="Optional" class="size-4 rounded border-line-strong bg-surface text-brand-600"></td>
                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                    <button type="button" wire:click="move({{ $i }}, -1)" class="p-1 text-subtle hover:text-muted" aria-label="Move up"><x-icon name="chevron-up" class="size-4" /></button>
                                    <button type="button" wire:click="move({{ $i }}, 1)" class="p-1 text-subtle hover:text-muted" aria-label="Move down"><x-icon name="chevron-down" class="size-4" /></button>
                                    <button type="button" wire:click="removeLine({{ $i }})" class="p-1 text-subtle hover:text-danger" aria-label="Remove"><x-icon name="trash" class="size-4" /></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('lines') <p class="px-4 py-2 text-xs text-danger">{{ $message }}</p> @enderror
            <x-slot:footer>
                <x-button variant="secondary" size="sm" wire:click="addLine" icon="plus" class="mr-auto">Add stage</x-button>
                @if ($canEdit)
                    <x-button type="submit" wire:loading.attr="disabled">Save route</x-button>
                @endif
            </x-slot:footer>
        </x-card>
    </form>
</div>
