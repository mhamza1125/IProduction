<div>
    <x-page-header title="Users" description="People who can sign in, and the roles that control what they can do.">
        @can('users.create')
            <x-button :href="route('core.users.create')" icon="plus">New user</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search name, email or username…" :export="auth()->user()->can('users.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </x-table.toolbar>

        <x-table empty="No users match your search.">
            <x-slot:head>
                <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Name</x-table.th>
                <x-table.th sortable="email" :sort-field="$sortField" :sort-direction="$sortDirection">Email</x-table.th>
                <x-table.th>Roles</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th sortable="last_login_at" :sort-field="$sortField" :sort-direction="$sortDirection">Last sign-in</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($users as $user)
                <tr wire:key="user-{{ $user->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                    <x-table.td class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->name }}</x-table.td>
                    <x-table.td>{{ $user->email }}</x-table.td>
                    <x-table.td>
                        <div class="flex flex-wrap gap-1">
                            @forelse ($user->roles as $role)
                                <x-badge color="blue">{{ $role->name }}</x-badge>
                            @empty
                                <span class="text-zinc-400">—</span>
                            @endforelse
                        </div>
                    </x-table.td>
                    <x-table.td>
                        <x-badge :color="$user->is_active ? 'green' : 'zinc'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    </x-table.td>
                    <x-table.td class="whitespace-nowrap text-zinc-500">{{ $user->last_login_at ? \App\Support\Format::datetime($user->last_login_at) : 'Never' }}</x-table.td>
                    <x-table.td align="right" class="whitespace-nowrap">
                        @can('users.edit')
                            <x-button variant="ghost" size="sm" :href="route('core.users.edit', $user)" icon="pencil">Edit</x-button>
                            <x-button variant="ghost" size="sm" wire:click="sendResetLink({{ $user->id }})" wire:confirm="Email a password reset link to {{ $user->email }}?">Reset password</x-button>
                            @unless ($user->is(auth()->user()))
                                <x-button variant="ghost" size="sm" wire:click="toggleActive({{ $user->id }})"
                                          wire:confirm="{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ $user->name }}?">
                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                </x-button>
                            @endunless
                        @endcan
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>

        @if ($users->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $users->links() }}</div>
        @endif
    </x-card>
</div>
