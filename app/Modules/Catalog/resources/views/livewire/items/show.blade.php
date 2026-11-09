<div>
    <x-page-header :title="$item->name" :description="$item->code.' · '.(\App\Modules\Catalog\Models\Item::TYPES[$item->type] ?? '')">
        <x-status :status="$item->is_active ? 'active' : 'inactive'" />
        @can('items.edit')
            <x-button variant="secondary" wire:click="toggleActive" wire:confirm="{{ $item->is_active ? 'Deactivate' : 'Activate' }} {{ $item->code }}?">{{ $item->is_active ? 'Deactivate' : 'Activate' }}</x-button>
            <x-button :href="route('catalog.items.edit', $item)" icon="pencil">Edit</x-button>
        @endcan
        @can('items.delete')
            <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete {{ $item->code }}? Items used anywhere can only be deactivated.">Delete</x-button>
        @endcan
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Details">
                <div class="flex gap-6">
                    @if ($item->image_path)
                        <img src="{{ Storage::disk('public')->url($item->image_path) }}" alt="" class="h-28 w-28 shrink-0 rounded-md object-cover">
                    @endif
                    <dl class="grid flex-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-people::detail label="Category" :value="$item->category?->name" />
                        <x-people::detail label="Stock unit" :value="$item->stockUnit->name" />
                        <x-people::detail label="Purchase / sales unit" :value="($item->purchaseUnit?->symbol ?? $item->stockUnit->symbol).' / '.($item->salesUnit?->symbol ?? $item->stockUnit->symbol)" />
                        <x-people::detail label="Use" :value="collect(['Bought' => $item->can_buy, 'Made' => $item->can_make, 'Sold' => $item->can_sell])->filter()->keys()->implode(', ')" />
                        <x-people::detail label="Selling price" :value="\App\Support\Format::money($item->default_selling_price, null, 4)" />
                        @if ($canSeeCost)
                            <x-people::detail label="Purchase cost" :value="\App\Support\Format::money($item->default_purchase_cost, 'PKR', 4)" />
                        @endif
                        <x-people::detail label="Minimum stock" :value="\App\Support\Format::qty($item->min_stock)" />
                        <x-people::detail label="Tax" :value="collect([$item->tax_rate !== null ? $item->tax_rate.'%' : null, $item->tax_notes])->filter()->implode(' · ')" />
                        <x-people::detail label="Lot tracking" :value="$item->track_by_lot ? 'Yes' : 'No'" />
                        <x-people::detail label="Description" :value="$item->description" class="sm:col-span-2 lg:col-span-3" />
                    </dl>
                </div>
            </x-card>

            <x-card title="Variants" :padding="false">
                <livewire:catalog::items.variants :item="$item" :key="'variants-'.$item->id" />
            </x-card>

            @if ($item->can_make)
                <div class="grid gap-6 lg:grid-cols-2">
                    <x-card title="Routes" :padding="false">
                        <x-slot:actions>
                            @can('routes.create')
                                <x-button size="sm" variant="secondary" :href="route('catalog.routes.create', ['item' => $item->id])" icon="plus">Add</x-button>
                            @endcan
                        </x-slot:actions>
                        <ul class="divide-y divide-line">
                            @forelse ($routes as $route)
                                <li class="flex items-center justify-between px-4 py-2.5 text-sm">
                                    <a href="{{ route('catalog.routes.edit', $route) }}" wire:navigate class="font-medium text-link hover:underline">{{ $route->name }}</a>
                                    <span class="text-xs text-subtle">{{ $route->stages_count }} stages @if ($route->is_default) · <x-badge color="blue">Default</x-badge> @endif</span>
                                </li>
                            @empty
                                <li class="px-4 py-3 text-sm text-subtle">No route yet.</li>
                            @endforelse
                        </ul>
                    </x-card>

                    <x-card title="BOM versions" :padding="false">
                        <x-slot:actions>
                            @can('boms.create')
                                <x-button size="sm" variant="secondary" wire:click="newBomVersion" icon="plus">New version</x-button>
                            @endcan
                        </x-slot:actions>
                        <ul class="divide-y divide-line">
                            @forelse ($boms as $bom)
                                <li class="flex items-center justify-between px-4 py-2.5 text-sm">
                                    <a href="{{ route('catalog.boms.edit', $bom) }}" wire:navigate class="font-medium text-link hover:underline">Version {{ $bom->version }}</a>
                                    <span class="flex items-center gap-2 text-xs text-subtle">@date($bom->effective_date) <x-status :status="$bom->is_active ? 'active' : $bom->status" /></span>
                                </li>
                            @empty
                                <li class="px-4 py-3 text-sm text-subtle">No BOM yet.</li>
                            @endforelse
                        </ul>
                    </x-card>
                </div>
            @endif

            <x-card title="Item-specific unit conversions">
                <livewire:catalog::items.conversions :item="$item" :key="'conv-'.$item->id" />
            </x-card>

            <x-card title="Attachments">
                <livewire:core::shared.attachments :model="$item" :key="'att-'.$item->id" />
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Notes"><livewire:core::shared.notes :model="$item" :key="'notes-'.$item->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$item" :key="'hist-'.$item->id" /></x-card>
        </div>
    </div>
</div>
