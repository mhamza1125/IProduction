<div>
    <x-page-header :title="$role ? 'Edit role' : 'New role'" description="Tick what people with this role may do. Admin always has every permission." />

    <form wire:submit="save" data-save-shortcut class="space-y-6">
        <x-card class="max-w-xl">
            <x-input label="Role name" wire:model="name" required autofocus placeholder="e.g. Store Keeper" />
        </x-card>

        @foreach ($groups as $group => $sets)
            <x-card :title="$group" :padding="false" wire:key="group-{{ $group }}">
                <div class="relative overflow-x-auto">
                    <table class="w-full min-w-[56rem] table-fixed text-sm">
                        <thead class="bg-sunken">
                            <tr>
                                <th class="w-56 px-4 py-2 text-left text-xs font-semibold uppercase text-subtle">Resource</th>
                                @foreach ($actions as $action)
                                    <th class="px-2 py-2 text-center text-xs font-semibold uppercase text-subtle">{{ $action }}</th>
                                @endforeach
                                <th class="w-14 px-2 py-2"><span class="sr-only">All</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($sets as $set)
                                @php($names = array_keys($set->permissions()))
                                <tr wire:key="set-{{ $set->key }}">
                                    <td class="whitespace-nowrap px-4 py-2 font-medium text-fg">{{ $set->label }}</td>
                                    @foreach ($actions as $action)
                                        <td class="px-2 py-2 text-center">
                                            @if (in_array($action, $set->actions, true))
                                                <input type="checkbox" wire:model="permissions" value="{{ $set->key }}.{{ $action }}"
                                                       aria-label="{{ $action }} {{ $set->label }}"
                                                       class="size-4 rounded border-line-strong text-brand-600 focus:ring-brand-600 bg-surface">
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-2 py-2 text-right">
                                        <button type="button" class="text-xs text-link hover:underline"
                                                x-on:click="$wire.permissions = [...new Set([...$wire.permissions, ...@js($names)])]">All</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach

        @error('permissions.*') <p class="text-xs text-danger">{{ $message }}</p> @enderror

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="route('core.roles.index')">Cancel</x-button>
            <x-button type="submit" wire:loading.attr="disabled">Save role</x-button>
        </div>
    </form>
</div>
