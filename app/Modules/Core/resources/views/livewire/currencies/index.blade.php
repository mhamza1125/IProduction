<div>
    <x-page-header title="Currencies" description="PKR is the base currency. For foreign-currency documents the exchange rate is entered on each transaction.">
        <x-button wire:click="create" icon="plus">New currency</x-button>
    </x-page-header>

    <x-card :padding="false">
        <x-table>
            <x-slot:head>
                <x-table.th>Code</x-table.th>
                <x-table.th>Name</x-table.th>
                <x-table.th>Symbol</x-table.th>
                <x-table.th align="right">Decimals</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>

            @foreach ($currencies as $currency)
                <tr wire:key="currency-{{ $currency->id }}">
                    <x-table.td class="font-mono font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $currency->code }}
                        @if ($currency->is_base) <x-badge color="blue" class="ml-1">Base</x-badge> @endif
                    </x-table.td>
                    <x-table.td>{{ $currency->name }}</x-table.td>
                    <x-table.td>{{ $currency->symbol }}</x-table.td>
                    <x-table.td align="right">{{ $currency->decimal_places }}</x-table.td>
                    <x-table.td>
                        <x-badge :color="$currency->is_active ? 'green' : 'zinc'">{{ $currency->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    </x-table.td>
                    <x-table.td align="right" class="whitespace-nowrap">
                        <x-button variant="ghost" size="sm" icon="pencil" wire:click="edit({{ $currency->id }})">Edit</x-button>
                        @unless ($currency->is_base)
                            <x-button variant="ghost" size="sm" wire:click="toggleActive({{ $currency->id }})">{{ $currency->is_active ? 'Deactivate' : 'Activate' }}</x-button>
                        @endunless
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit currency' : 'New currency'">
        <form wire:submit="save" id="currency-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Code" wire:model="code" maxlength="3" required placeholder="USD" help="ISO 4217, 3 letters" />
            <x-input label="Symbol" wire:model="symbol" maxlength="10" placeholder="$" />
            <x-input label="Name" wire:model="name" required class="sm:col-span-2" />
            <x-input label="Decimal places" type="number" min="0" max="4" wire:model="decimal_places" required />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="currency-form" wire:loading.attr="disabled">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
