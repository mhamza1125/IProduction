<div>
    <x-page-header title="Numbering" description="Every document gets a unique number. The year part comes from the document date." />

    <x-card :padding="false">
        <x-table empty="No document types yet. They appear here as modules are added.">
            <x-slot:head>
                <x-table.th>Document</x-table.th>
                <x-table.th>Next number</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>
            @foreach ($rows as $row)
                <tr wire:key="seq-{{ $row['key'] }}">
                    <x-table.td>{{ $row['label'] }}</x-table.td>
                    <x-table.td class="font-mono">{{ $row['next'] }}</x-table.td>
                    <x-table.td align="right">
                        @can('settings.edit')
                            <x-button variant="ghost" size="sm" icon="pencil" wire:click="edit('{{ $row['key'] }}')">Edit</x-button>
                        @endcan
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    <x-modal wire:model="showForm" title="Edit numbering">
        <form wire:submit="save" id="numbering-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Prefix" wire:model="prefix" required maxlength="20" />
            <x-select label="Separator" wire:model="separator" :options="['-' => '-  (SO-26-0001)', '/' => '/  (SO/26/0001)', '.' => '.  (SO.26.0001)']" />
            <x-select label="Year" wire:model="year_format" :options="$yearFormats" />
            <x-input label="Digits" type="number" min="1" max="10" wire:model="padding" required />
            <x-input label="Next number (this year)" type="number" min="1" wire:model="next_number" required class="sm:col-span-2"
                     help="Lower it only if you are sure no document already uses those numbers: numbers must stay unique." />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="numbering-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
