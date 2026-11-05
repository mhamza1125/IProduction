<div>
    <x-page-header title="Attributes" description="Ways items vary, such as Color and Size. Items pick the values they come in to create variants." />

    @can('attributes.create')
        <form wire:submit="addAttribute" class="mb-6 flex max-w-md items-end gap-2">
            <x-input label="New attribute" wire:model="newAttribute" placeholder="e.g. Color" class="flex-1" />
            <x-button type="submit">Add</x-button>
        </form>
    @endcan

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($attributeList as $attribute)
            <x-card :title="$attribute->name" wire:key="a-{{ $attribute->id }}">
                @can('attributes.delete')
                    <x-slot:actions>
                        <button type="button" wire:click="removeAttribute({{ $attribute->id }})" wire:confirm="Delete {{ $attribute->name }}?" class="text-xs text-zinc-500 hover:text-red-600">Delete</button>
                    </x-slot:actions>
                @endcan
                <div class="flex flex-wrap gap-2">
                    @foreach ($attribute->values as $value)
                        <span class="inline-flex items-center gap-1 rounded-md bg-zinc-100 px-2 py-1 text-sm dark:bg-zinc-800" wire:key="v-{{ $value->id }}">
                            {{ $value->value }}
                            @can('attributes.delete')
                                <button type="button" wire:click="removeValue({{ $value->id }})" class="text-zinc-400 hover:text-red-600" aria-label="Remove {{ $value->value }}"><x-icon name="x" class="size-3.5" /></button>
                            @endcan
                        </span>
                    @endforeach
                </div>
                @can('attributes.edit')
                    <form wire:submit="addValue({{ $attribute->id }})" class="mt-3 flex gap-2">
                        <input type="text" wire:model="newValue.{{ $attribute->id }}" placeholder="Add value…" aria-label="New {{ $attribute->name }} value"
                               class="block w-full rounded-md border-0 bg-white px-3 py-1.5 text-sm ring-1 ring-inset ring-zinc-300 focus:ring-2 focus:ring-brand-600 dark:bg-zinc-900 dark:ring-zinc-700">
                        <x-button type="submit" size="sm" variant="secondary">Add</x-button>
                    </form>
                    @error('value') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @endcan
            </x-card>
        @empty
            <p class="text-sm text-zinc-500">No attributes yet.</p>
        @endforelse
    </div>
</div>
