<div>
    <x-page-header title="Production stages" description="Steps a product goes through, e.g. Stitching → Stamping → Leather Attach. Routes on items use these.">
        @can('stages.create')
            <x-button wire:click="create" icon="plus">New stage</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false" class="max-w-3xl">
        <x-table empty="No stages yet.">
            <x-slot:head>
                <x-table.th align="right">Order</x-table.th>
                <x-table.th>Stage</x-table.th>
                <x-table.th>Description</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>
            @foreach ($stages as $stage)
                <tr wire:key="s-{{ $stage->id }}">
                    <x-table.td align="right">{{ $stage->sort_order }}</x-table.td>
                    <x-table.td class="font-medium">{{ $stage->name }}</x-table.td>
                    <x-table.td class="text-zinc-500">{{ $stage->description }}</x-table.td>
                    <x-table.td><x-status :status="$stage->is_active ? 'active' : 'inactive'" /></x-table.td>
                    <x-table.td align="right" class="whitespace-nowrap">
                        @can('stages.edit')
                            <x-button variant="ghost" size="sm" wire:click="edit({{ $stage->id }})">Edit</x-button>
                        @endcan
                        @can('stages.delete')
                            <x-button variant="ghost" size="sm" wire:click="delete({{ $stage->id }})" wire:confirm="Delete {{ $stage->name }}?">Delete</x-button>
                        @endcan
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit stage' : 'New stage'">
        <form wire:submit="save" id="stage-form" class="grid gap-4">
            <x-input label="Name" wire:model="name" required />
            <x-textarea label="Description" wire:model="description" rows="2" />
            <x-input label="Sort order" type="number" min="0" wire:model="sort_order" required class="w-32" />
            <x-checkbox wire:model="is_active" label="Active" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="stage-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
