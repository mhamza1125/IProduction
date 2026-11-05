<?php

namespace App\Modules\Inventory\Livewire\Adjustments;

use App\Modules\Inventory\Actions\PostStockAdjustment;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockStatus;
use App\Support\Documents\Document;
use App\Support\Livewire\HandlesDocumentActions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use HandlesDocumentActions;

    #[Locked]
    public StockAdjustment $adjustment;

    public function mount(StockAdjustment $adjustment): void
    {
        $this->adjustment = $adjustment;
    }

    protected function document(): Model&Document
    {
        return $this->adjustment;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(PostStockAdjustment::class)($this->adjustment, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->adjustment->refresh();
    }

    public function delete(): void
    {
        $this->authorize('stock_adjustments.delete');
        abort_unless($this->adjustment->isDraft(), 403);
        $this->adjustment->lines()->delete();
        $this->adjustment->delete();

        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('inventory.adjustments.index', navigate: true);
    }

    public function render(): View
    {
        return view('inventory::livewire.adjustments.show', [
            'lines' => $this->adjustment->lines()->with(['variant.item.stockUnit', 'stage', 'location', 'lot'])->get(),
            'statuses' => StockStatus::LABELS,
            'movements' => StockMovement::query()->where('source_type', $this->adjustment->getMorphClass())
                ->where('source_id', $this->adjustment->id)->with(['lot', 'location'])->get(),
        ])->title($this->adjustment->number);
    }
}
