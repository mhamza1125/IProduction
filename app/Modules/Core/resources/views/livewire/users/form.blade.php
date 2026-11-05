<div>
    <x-page-header :title="$user ? 'Edit user' : 'New user'" :description="$user?->email" />

    <form wire:submit="save" data-save-shortcut class="max-w-2xl">
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Name" wire:model="name" required autofocus class="sm:col-span-2" />
                <x-input label="Email" type="email" wire:model="email" required autocomplete="off" />
                <x-input label="Username" wire:model="username" autocomplete="off" help="Optional. Can be used instead of the email to sign in." />
                <x-input label="{{ $user ? 'New password' : 'Password' }}" type="password" wire:model="password"
                         :required="! $user" autocomplete="new-password"
                         :help="$user ? 'Leave blank to keep the current password.' : null" />
                <x-input label="Confirm password" type="password" wire:model="password_confirmation" :required="! $user" autocomplete="new-password" />

                <div class="sm:col-span-2">
                    <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Roles</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @forelse ($allRoles as $role)
                            <x-checkbox wire:model="roles" value="{{ $role }}" :label="$role" />
                        @empty
                            <p class="text-sm text-zinc-500">No roles yet. <a href="{{ route('core.roles.create') }}" class="text-brand-600 hover:underline">Create one</a>.</p>
                        @endforelse
                    </div>
                    @error('roles') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <x-checkbox wire:model="is_active" label="Active" help="Inactive users cannot sign in." />
                    @error('is_active') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-slot:footer>
                <x-button variant="secondary" :href="route('core.users.index')">Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled">Save</x-button>
            </x-slot:footer>
        </x-card>
    </form>
</div>
