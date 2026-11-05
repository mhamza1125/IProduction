<div>
    <x-page-header title="Chart of accounts" description="Accounts marked System are used by automatic postings and cannot be deleted or change type.">
        @can('chart_of_accounts.create')
            <x-button wire:click="create" icon="plus">New account</x-button>
        @endcan
    </x-page-header>

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search code or name…" :export="auth()->user()->can('chart_of_accounts.export')">
            <select wire:model.live="type" aria-label="Type" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All types</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-table.toolbar>

        <x-table>
            <x-slot:head>
                <x-table.th sortable="code" :sort-field="$sortField" :sort-direction="$sortDirection">Code</x-table.th>
                <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Name</x-table.th>
                <x-table.th sortable="type" :sort-field="$sortField" :sort-direction="$sortDirection">Type</x-table.th>
                <x-table.th>Parent</x-table.th>
                <x-table.th>Status</x-table.th>
                <x-table.th align="right"><span class="sr-only">Actions</span></x-table.th>
            </x-slot:head>
            @foreach ($accounts as $account)
                <tr wire:key="a-{{ $account->id }}">
                    <x-table.td class="font-mono">{{ $account->code }}</x-table.td>
                    <x-table.td class="font-medium">
                        {{ $account->name }}
                        @if ($account->system_key) <x-badge color="blue" class="ml-1">System</x-badge> @endif
                    </x-table.td>
                    <x-table.td>{{ $types[$account->type] ?? $account->type }}</x-table.td>
                    <x-table.td class="text-zinc-500">{{ $account->parent?->code }}</x-table.td>
                    <x-table.td><x-status :status="$account->is_active ? 'active' : 'inactive'" /></x-table.td>
                    <x-table.td align="right" class="whitespace-nowrap">
                        @can('chart_of_accounts.edit')
                            <x-button variant="ghost" size="sm" wire:click="edit({{ $account->id }})">Edit</x-button>
                        @endcan
                        @if (! $account->system_key)
                            @can('chart_of_accounts.delete')
                                <x-button variant="ghost" size="sm" wire:click="delete({{ $account->id }})" wire:confirm="Delete {{ $account->code }} {{ $account->name }}?">Delete</x-button>
                            @endcan
                        @endif
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($accounts->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $accounts->links() }}</div>
        @endif
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit account' : 'New account'">
        <form wire:submit="save" id="account-form" class="grid gap-4 sm:grid-cols-2">
            <x-input label="Code" wire:model="code" required />
            <x-select label="Type" wire:model="accountType" :options="$types" required />
            <x-input label="Name" wire:model="name" required class="sm:col-span-2" />
            <x-select label="Parent account" wire:model="parent_id" :options="$parents" placeholder="None" class="sm:col-span-2" />
            <x-textarea label="Description" wire:model="description" rows="2" class="sm:col-span-2" />
            <x-checkbox wire:model="is_active" label="Active" class="sm:col-span-2" />
        </form>
        <x-slot:footer>
            <x-button variant="secondary" x-on:click="open = false">Cancel</x-button>
            <x-button type="submit" form="account-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
</div>
