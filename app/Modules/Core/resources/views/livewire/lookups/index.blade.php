<div>
    <x-page-header title="Lookups" description="Short pick-lists used across the system.">
        @can('lookups.create')
            <x-button wire:click="create" icon="plus">Add {{ \Illuminate\Support\Str::singular(mb_strtolower($types[$type])) }}</x-button>
        @endcan
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
        <nav class="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Lookup lists">
            @foreach ($types as $key => $label)
                <button type="button" wire:click="$set('type', '{{ $key }}')"
                        @class([
                            'whitespace-nowrap rounded-md px-3 py-2 text-left text-sm font-medium',
                            'bg-raised text-fg shadow-sm ring-1 ring-line' => $type === $key,
                            'text-muted hover:bg-hover' => $type !== $key,
                        ])>{{ $label }}</button>
            @endforeach
        </nav>

        <x-card :padding="false">
            <x-table empty="Nothing in this list yet.">
                <x-slot:head>
                    <x-table.th>Name</x-table.th>
                    <x-table.th align="right">Order</x-table.th>
                    <x-table.th>Status</x-table.th>
                    <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
                </x-slot:head>
                @foreach ($lookups as $lookup)
                    <tr wire:key="l-{{ $lookup->id }}">
                        <x-table.td class="font-medium">{{ $lookup->name }}</x-table.td>
                        <x-table.td align="right">{{ $lookup->sort_order }}</x-table.td>
                        <x-table.td><x-status :status="$lookup->is_active ? 'active' : 'inactive'" /></x-table.td>
                        <x-table.td align="right" class="whitespace-nowrap">
                            @can('lookups.edit')
                                <x-button variant="ghost" size="sm" wire:click="edit({{ $lookup->id }})">Edit</x-button>
                            @endcan
                            @can('lookups.delete')
                                <x-button variant="ghost" size="sm" wire:click="delete({{ $lookup->id }})" wire:confirm="Delete {{ $lookup->name }}?">Delete</x-button>
                            @endcan
                        </x-table.td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    </div>

    <x-modal wire:model="showForm" :title="$types[$type]">
        <form wire:submit="save" id="lookup-form" class="grid gap-4">
            <x-input label="Name" wire:model="name" required />
            <x-input label="Sort order" type="number" min="0" wire:model="sort_order" class="w-32" />
            <x-checkbox wire:model="is_active" label="Active" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="lookup-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
