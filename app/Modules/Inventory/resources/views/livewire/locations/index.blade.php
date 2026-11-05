<div>
    <x-page-header title="Locations" description="Main Store, Production Floor and one location per contractor are created automatically. Stock at a contractor still belongs to the company.">
        @can('locations.create')
            <x-button wire:click="create" icon="plus">New store location</x-button>
        @endcan
    </x-page-header>
    <x-card :padding="false" class="max-w-3xl">
        <x-table>
            <x-slot:head>
                <x-table.th>Code</x-table.th><x-table.th>Name</x-table.th><x-table.th>Type</x-table.th><x-table.th>Status</x-table.th><x-table.th><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>
            @foreach ($locations as $l)
                <tr wire:key="loc-{{ $l->id }}">
                    <x-table.td class="font-mono">{{ $l->code }}</x-table.td>
                    <x-table.td>{{ $l->name }}</x-table.td>
                    <x-table.td>{{ $types[$l->type] }} @if ($l->system_key)<x-badge color="blue">System</x-badge>@endif</x-table.td>
                    <x-table.td><x-status :status="$l->is_active ? 'active' : 'inactive'" /></x-table.td>
                    <x-table.td align="right">
                        @if ($l->type === 'store' && ! $l->system_key)
                            @can('locations.edit') <x-button variant="ghost" size="sm" wire:click="edit({{ $l->id }})">Edit</x-button> @endcan
                        @endif
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
    <x-modal wire:model="showForm" :title="$editingId ? 'Edit location' : 'New store location'">
        <form wire:submit="save" id="loc-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Code" wire:model="code" required />
            <x-input label="Name" wire:model="name" required />
            <x-checkbox wire:model="is_active" label="Active" class="sm:col-span-2" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="loc-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
