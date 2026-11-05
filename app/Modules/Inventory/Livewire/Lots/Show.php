<?php

namespace App\Modules\Inventory\Livewire\Lots;

use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockStatus;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Lot traceability: where it came from, every movement, what it was made
 * from (inputs), what was made from it (outputs) and the documents that used it.
 */
class Show extends Component
{
    #[Locked]
    public Lot $lot;

    public function mount(Lot $lot): void
    {
        $this->lot = $lot;
    }

    public function render(): View
    {
        $this->lot->loadMissing(['variant.item.stockUnit', 'vendor', 'contractor', 'source']);

        return view('inventory::livewire.lots.show', [
            'movements' => $this->lot->movements()->with(['stage', 'location', 'source', 'user'])->orderBy('movement_date')->orderBy('id')->get(),
            'balances' => StockBalance::query()->where('lot_id', $this->lot->id)->where('quantity', '>', 0)->with(['stage', 'location'])->get(),
            'inputs' => $this->lot->inputs()->with('sourceLot.variant.item', 'document')->get(),
            'outputs' => $this->lot->outputs()->with('lot.variant.item', 'document')->get(),
            'statusLabels' => StockStatus::LABELS,
        ])->title('Lot '.$this->lot->lot_number);
    }
}
