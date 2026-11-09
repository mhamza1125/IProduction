<div>
    <x-page-header title="Settings" description="Choose which documents must be approved before they can be posted." />
    @include('core::settings-tabs')

    <form wire:submit="save" data-save-shortcut class="max-w-2xl">
        <x-card :padding="false">
            <x-table empty="No document types yet. They appear here as modules are added.">
                <x-slot:head>
                    <x-table.th>Document</x-table.th>
                    <x-table.th align="center">Requires approval</x-table.th>
                </x-slot:head>
                @foreach ($types as $key => $type)
                    <tr wire:key="type-{{ $key }}">
                        <x-table.td>{{ $type->label }}</x-table.td>
                        <x-table.td align="center">
                            <input type="checkbox" wire:model="required.{{ $key }}" aria-label="{{ $type->label }} requires approval"
                                   class="size-4 rounded border-line-strong text-brand-600 focus:ring-brand-600 bg-surface">
                        </x-table.td>
                    </tr>
                @endforeach
            </x-table>
            @can('settings.edit')
                <x-slot:footer><x-button type="submit">Save</x-button></x-slot:footer>
            @endcan
        </x-card>
    </form>
</div>
