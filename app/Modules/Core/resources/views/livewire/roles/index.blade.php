<div>
    <x-page-header title="Roles" description="A role is a named set of permissions. Give users roles, not individual permissions.">
        <x-button :href="route('core.roles.create')" icon="plus">New role</x-button>
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
                    <x-table.td class="font-medium text-zinc-900 dark:text-zinc-100">{{ $role->name }}</x-table.td>
                    <x-table.td align="right">{{ $role->name === $superAdmin ? 'All' : $role->permissions_count }}</x-table.td>
                    <x-table.td align="right">{{ $role->users_count }}</x-table.td>
                    <x-table.td align="right" class="whitespace-nowrap">
                        @if ($role->name !== $superAdmin)
                            <x-button variant="ghost" size="sm" :href="route('core.roles.edit', $role)" icon="pencil">Edit</x-button>
                            <x-button variant="ghost" size="sm" icon="trash" wire:click="delete({{ $role->id }})" wire:confirm="Delete the {{ $role->name }} role?">Delete</x-button>
                        @else
                            <span class="text-xs text-zinc-400">Built in</span>
                        @endif
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    </x-card>
</div>
