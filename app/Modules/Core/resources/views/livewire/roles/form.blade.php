<div>
    <x-page-header :title="$role ? 'Edit role' : 'New role'" />

    <form wire:submit="save" data-save-shortcut class="max-w-3xl space-y-6">
        <x-card>
            <x-input label="Role name" wire:model="name" required autofocus placeholder="e.g. Store Keeper" />
        </x-card>

        @foreach ($groups as $module => $permissions)
            <x-card :title="$module" wire:key="group-{{ $module }}">
                <x-slot:actions>
                    <button type="button" class="text-xs text-brand-600 hover:underline"
                            x-on:click="$wire.permissions = [...new Set([...$wire.permissions, ...@js(array_keys($permissions))])]">Select all</button>
                </x-slot:actions>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($permissions as $permission => $description)
                        <x-checkbox wire:model="permissions" value="{{ $permission }}" :label="$description" :help="$permission" wire:key="perm-{{ $permission }}" />
                    @endforeach
                </div>
            </x-card>
        @endforeach

        @error('permissions.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="route('core.roles.index')">Cancel</x-button>
            <x-button type="submit" wire:loading.attr="disabled">Save role</x-button>
        </div>
    </form>
</div>
