<?php

namespace App\Modules\Inventory\Livewire\Reservations;

use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Services\StockLedger;
use App\Support\Documents\DocumentException;
use App\Support\Livewire\WithDataTable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reservations hold free stock for sales order lines. They are created from
 * Planning; here users with permission release them, with a reason.
 */
#[Title('Reservations')]
class Index extends Component
{
    use WithDataTable;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Locked]
    public ?int $releasing = null;

    public string $reason = '';

    protected function sortable(): array
    {
        return ['created_at'];
    }

    public function confirmRelease(int $id): void
    {
        $this->authorize('reservations.cancel');
        $this->releasing = $id;
        $this->reason = '';
    }

    public function release(StockLedger $stock): void
    {
        $this->authorize('reservations.cancel');
        $this->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $stock->release(Reservation::query()->findOrFail($this->releasing), $this->reason);
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->releasing = null;
        $this->dispatch('toast', message: 'Reservation released.');
    }

    public function render(): View
    {
        $query = Reservation::query()->with(['variant.item', 'lot', 'demand', 'creator'])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status));

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('variant', fn ($v) => $v->where('sku', 'like', $like)->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like)));
        }

        return view('inventory::livewire.reservations.index', [
            'reservations' => $this->applySorting($query, 'created_at', 'desc')->paginate($this->perPage),
        ]);
    }
}
