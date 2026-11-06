<div>
    <x-page-header title="Cash and bank accounts" description="The Cash Book is permanent. Foreign-currency accounts keep their balance in that currency.">
        @can('accounts.create')
            <x-button wire:click="create" icon="plus">New account</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table empty="No accounts.">
            <x-slot:head>
                <x-table.th>Account</x-table.th><x-table.th>Type</x-table.th><x-table.th>Bank details</x-table.th><x-table.th>Currency</x-table.th>
                @if ($canSeeBalance) <x-table.th align="right">Balance</x-table.th> @endif
                <x-table.th>Status</x-table.th><x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>
            @foreach ($accounts as $a)
                <tr wire:key="ma-{{ $a->id }}">
                    <x-table.td class="font-medium">{{ $a->name }} @if ($a->system_key)<x-badge color="blue">Permanent</x-badge>@endif <div class="font-mono text-xs text-zinc-500">{{ $a->glAccount->code }}</div></x-table.td>
                    <x-table.td>{{ $types[$a->type] }}</x-table.td>
                    <x-table.td class="text-xs text-zinc-500">{{ collect([$a->bank_name, $a->branch, $a->account_number, $a->iban])->filter()->implode(' · ') }}</x-table.td>
                    <x-table.td class="font-mono">{{ $a->currency_code }}</x-table.td>
                    @if ($canSeeBalance) <x-table.td align="right" class="font-semibold">@money($balances[$a->id], $a->currency_code)</x-table.td> @endif
                    <x-table.td><x-status :status="$a->is_active ? 'active' : 'inactive'" /></x-table.td>
                    <x-table.td align="right" class="whitespace-nowrap">
                        @if ($canSeeBalance)
                            @can('ledgers.view') <x-button variant="ghost" size="sm" :href="route('finance.ledgers', ['type' => 'account', 'id' => $a->id])">Statement</x-button> @endcan
                        @endif
                        @can('accounts.edit') <x-button variant="ghost" size="sm" wire:click="edit({{ $a->id }})">Edit</x-button> @endcan
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit account' : 'New account'" max-width="xl">
        <form wire:submit="save" id="acct-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Name" wire:model="name" required class="sm:col-span-2" />
            <x-select label="Type" wire:model="type" :options="$types" required />
            <x-select label="Currency" wire:model="currency_code" :options="$currencies" required />
            <x-input label="Bank name" wire:model="bank_name" />
            <x-input label="Branch" wire:model="branch" />
            <x-input label="Account title" wire:model="account_title" />
            <x-input label="Account number" wire:model="account_number" />
            <x-input label="IBAN" wire:model="iban" class="sm:col-span-2" />
            <x-checkbox wire:model="allow_overdraft" label="Allow overdraft" help="Otherwise payments cannot exceed the balance." />
            <x-checkbox wire:model="is_active" label="Active" />
            <x-textarea label="Notes" wire:model="notes" rows="2" class="sm:col-span-2" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="acct-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
