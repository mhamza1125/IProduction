<?php

namespace App\Modules\Catalog\Livewire\Items;

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Unit;
use App\Modules\Core\Models\UnitConversion;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Unit conversions that only apply to one item, e.g. 1 roll of this leather = 25 metres.
 */
class Conversions extends Component
{
    #[Locked]
    public Item $item;

    public ?int $from_unit_id = null;

    public ?int $to_unit_id = null;

    public string $factor = '';

    public function mount(Item $item): void
    {
        $this->item = $item;
        $this->to_unit_id = $item->stock_unit_id;
    }

    public function add(): void
    {
        $this->authorize('items.edit');
        $activeUnit = Rule::exists('units', 'id')->where('is_active', true)->whereNull('deleted_at');

        $data = $this->validate([
            'from_unit_id' => ['required', $activeUnit],
            'to_unit_id' => ['required', 'different:from_unit_id', $activeUnit],
            'factor' => ['required', 'numeric', 'gt:0', 'decimal:0,6'],
        ]);

        UnitConversion::query()->updateOrCreate(
            ['item_id' => $this->item->id, 'from_unit_id' => $data['from_unit_id'], 'to_unit_id' => $data['to_unit_id']],
            ['factor' => $data['factor']],
        );

        $this->reset('from_unit_id', 'factor');
    }

    public function remove(int $id): void
    {
        $this->authorize('items.edit');
        UnitConversion::query()->where('item_id', $this->item->id)->findOrFail($id)->delete();
    }

    public function render(): View
    {
        return view('catalog::livewire.items.conversions', [
            'conversions' => UnitConversion::query()->where('item_id', $this->item->id)->with(['fromUnit', 'toUnit'])->get(),
            'units' => Unit::query()->active()->orderBy('name')->pluck('name', 'id'),
            'canEdit' => auth()->user()?->can('items.edit') ?? false,
        ]);
    }
}
