<div>
    <ul class="mb-3 space-y-1 text-sm">
        @forelse ($conversions as $c)
            <li class="flex items-center justify-between" wire:key="ic-{{ $c->id }}">
                <span>1 {{ $c->fromUnit->name }} = @qty($c->factor) {{ $c->toUnit->name }}</span>
                @if ($canEdit)
                    <button type="button" wire:click="remove({{ $c->id }})" class="text-xs text-zinc-500 hover:text-red-600">Remove</button>
                @endif
            </li>
        @empty
            <li class="text-zinc-500">None. General conversions from Settings › Units apply.</li>
        @endforelse
    </ul>
    @if ($canEdit)
        <form wire:submit="add" class="grid items-end gap-3 sm:grid-cols-[1fr_8rem_1fr_auto]">
            <x-select label="1 ×" wire:model="from_unit_id" :options="$units" placeholder="Unit…" />
            <x-input label="equals" type="number" step="any" wire:model="factor" />
            <x-select label="of" wire:model="to_unit_id" :options="$units" placeholder="Unit…" />
            <x-button type="submit" variant="secondary">Add</x-button>
        </form>
    @endif
</div>
