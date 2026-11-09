<div>
    <x-page-header title="Roles" description="A role is a named set of permissions. Users can have several roles.">
        @can('roles.create')
            <x-button :href="route('core.roles.create')" icon="plus">New role</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table>
            <x-slot:head>
                <x-table.th>Role</x-table.th>
                <x-table.th align="right">Permissions</x-table.th>
                <x-table.th align="right">Users</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($roles as $role)
                <tr wire:key="role-{{ $role->id }}">
                    <x-table.td class="font-medium text-fg">{{ $role->name }}</x-table.td>
                    <x-table.td align="right">{{ $role->name === $adminRole ? 'All' : $role->permissions_count }}</x-table.td>
                    <x-table.td align="right">{{ $role->users_count }}</x-table.td>
                    <x-table.td align="right" class="whitespace-nowrap">
                        @if ($role->name !== $adminRole)
                            @can('roles.edit')
                                <x-button variant="ghost" size="sm" :href="route('core.roles.edit', $role)" icon="pencil">Edit</x-button>
                            @endcan
                            @can('roles.delete')
                                <x-button variant="ghost" size="sm" icon="trash" wire:click="delete({{ $role->id }})" wire:confirm="Delete the {{ $role->name }} role?">Delete</x-button>
                            @endcan
                        @else
                            <span class="text-xs text-subtle">Built in</span>
                        @endif
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
