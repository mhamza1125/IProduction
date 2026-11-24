<div>
    <x-page-header :title="$asset ? $asset->code.' · '.$asset->name : 'New asset'" description="Record only: it does not post to accounts or stock." />
    <div class="grid gap-6 xl:grid-cols-3">
        <form wire:submit="save" data-save-shortcut class="xl:col-span-2">
            <x-card>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-input label="Code" wire:model="code" placeholder="Automatic" />
                    <x-input label="Name" wire:model="name" required class="lg:col-span-2" />
                    <x-input label="Category" wire:model="category" placeholder="Vehicle, AC, computer, land…" />
                    <x-date-input label="Purchase date" wire:model="purchase_date" />
                    <x-input label="Value (PKR)" type="number" step="any" min="0" wire:model="value" />
                    <x-input label="Location" wire:model="location" />
                    <x-select label="Assigned to" wire:model="assigned_employee_id" :options="$employees" placeholder="—" />
                    <x-input label="Serial number" wire:model="serial_number" />
                    <x-select label="Status" wire:model="status" :options="\App\Modules\Finance\Models\Asset::STATUSES" />
                    <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
                    <x-input label="Useful life (months)" type="number" min="1" wire:model="useful_life_months" help="Leave empty for assets that are not depreciated (e.g. land)." />
                    <x-input label="Salvage value (PKR)" wire:model="salvage_value" inputmode="decimal" help="Expected value at the end of its life." />
                    <x-date-input label="Depreciation starts" wire:model="depreciation_start" help="Usually the month it was put to use." />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    @if ($asset) @can('assets.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Remove this asset from the register?">Remove</x-button> @endcan @endif
                    <x-button variant="ghost" :href="route('finance.assets.index')">Back</x-button>
                    <x-button type="submit">Save</x-button>
                </div>
            </x-card>
        </form>
        @if ($asset)
            <div class="space-y-6">
                @if ($asset->useful_life_months)
                    <x-card title="Depreciation" :description="'Straight line: '.\App\Support\Format::money($monthly ?? '0').' a month.'" :padding="false">
                        <div class="grid grid-cols-3 gap-4 px-4 py-3 text-sm sm:px-6">
                            <div><div class="text-xs text-subtle">Cost</div><div class="tabular text-fg">@money($asset->value)</div></div>
                            <div><div class="text-xs text-subtle">Accumulated</div><div class="tabular text-fg">@money($accumulated)</div></div>
                            <div><div class="text-xs text-subtle">Book value</div><div class="tabular font-semibold text-fg">@money($bookValue)</div></div>
                        </div>
                        <x-table empty="Not depreciated yet. Run depreciation from the asset register.">
                            <x-slot:head><x-table.th>Month</x-table.th><x-table.th align="right">Amount</x-table.th><x-table.th>Journal</x-table.th></x-slot:head>
                            @foreach ($history as $d)
                                <tr wire:key="dep-{{ $d->id }}"><x-table.td>{{ $d->period->format('F Y') }}</x-table.td><x-table.td align="right">@money($d->amount)</x-table.td>
                                    <x-table.td><a href="{{ route('finance.journals.show', $d->journal_id) }}" wire:navigate class="font-mono text-xs text-link hover:underline">{{ $d->journal->number }}</a></x-table.td></tr>
                            @endforeach
                        </x-table>
                    </x-card>
                @endif
                <x-card title="Attachments"><livewire:core::shared.attachments :model="$asset" :key="'a-'.$asset->id" /></x-card>
                <x-card title="Notes"><livewire:core::shared.notes :model="$asset" :key="'n-'.$asset->id" /></x-card>
                <x-card title="History"><livewire:core::shared.activity-history :model="$asset" :key="'h-'.$asset->id" /></x-card>
            </div>
        @endif
    </div>
</div>
