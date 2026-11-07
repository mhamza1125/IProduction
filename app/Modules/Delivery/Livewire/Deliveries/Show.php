<?php

namespace App\Modules\Delivery\Livewire\Deliveries;

use App\Modules\Delivery\Models\Carton;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryReturn;
use App\Modules\Delivery\Models\InvoiceLine;
use App\Modules\Delivery\Services\Deliveries;
use App\Modules\Delivery\Services\Packing;
use App\Support\Documents\DocumentException;
use App\Support\Documents\PeriodLock;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Delivery $delivery;

    public string $reason = '';

    /** @var array<string, string> */
    public array $carton = ['carton_type' => '', 'length' => '', 'width' => '', 'height' => '', 'gross_weight' => '', 'net_weight' => '', 'marks' => '', 'notes' => ''];

    /** @var array<int, string> delivery line id => quantity in the carton */
    public array $contents = [];

    public int $repeat = 1;

    public function mount(Delivery $delivery): void
    {
        $this->delivery = $delivery;
    }

    private function attempt(callable $action, string $message): bool
    {
        try {
            $action();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return false;
        }
        $this->delivery->refresh();
        $this->dispatch('toast', message: $message);

        return true;
    }

    public function allocate(Deliveries $deliveries): void
    {
        $this->authorize('deliveries.edit');
        $this->attempt(fn () => $deliveries->allocate($this->delivery), 'Stock allocated and reserved. Now pack the delivery.');
    }

    public function unallocate(Deliveries $deliveries): void
    {
        $this->authorize('deliveries.edit');
        $this->attempt(fn () => $deliveries->unallocate($this->delivery, $this->reason), 'Back to draft; reservations made for it were released.');
    }

    public function cancel(Deliveries $deliveries): void
    {
        $this->authorize('deliveries.cancel');
        $this->attempt(fn () => $deliveries->cancel($this->delivery, $this->reason), 'Cancelled.');
    }

    public function delete(): void
    {
        $this->authorize('deliveries.delete');
        abort_unless($this->delivery->isDraft(), 403);
        $this->delivery->lines()->delete();
        $this->delivery->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('delivery.deliveries.index', navigate: true);
    }

    public function addCarton(Packing $packing): void
    {
        $this->authorize('packing_lists.edit');
        $this->validate([
            'carton.length' => ['nullable', 'numeric', 'min:0'], 'carton.width' => ['nullable', 'numeric', 'min:0'], 'carton.height' => ['nullable', 'numeric', 'min:0'],
            'carton.gross_weight' => ['nullable', 'numeric', 'min:0'], 'carton.net_weight' => ['nullable', 'numeric', 'min:0'],
            'contents.*' => ['nullable', 'numeric', 'min:0'], 'repeat' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        if ($this->attempt(fn () => $packing->addCarton($this->delivery, $this->carton, $this->contents, $this->repeat), $this->repeat > 1 ? "{$this->repeat} cartons added." : 'Carton added.')) {
            $this->contents = [];
            $this->repeat = 1;
        }
    }

    public function removeCarton(int $id, Packing $packing): void
    {
        $this->authorize('packing_lists.edit');
        $carton = Carton::query()->whereKey($id)->whereHas('packingList', fn ($q) => $q->where('delivery_id', $this->delivery->id))->firstOrFail();
        $this->attempt(fn () => $packing->removeCarton($carton), 'Carton removed.');
    }

    public function completePacking(Packing $packing): void
    {
        $this->authorize('packing_lists.edit');
        $this->attempt(fn () => $packing->complete($this->delivery), 'Packing complete. Ready to dispatch.');
    }

    public function reopenPacking(Packing $packing): void
    {
        $this->authorize('packing_lists.edit');
        $this->attempt(fn () => $packing->reopen($this->delivery), 'Packing reopened.');
    }

    public function dispatchDelivery(Deliveries $deliveries, PeriodLock $lock): void
    {
        $this->authorize('deliveries.post');
        $override = null;
        if ($lock->isLocked($this->delivery->delivery_date)) {
            $this->authorize('period_lock.override');
            $override = $this->reason;
        }
        $this->attempt(fn () => $deliveries->dispatch($this->delivery, $override), 'Dispatched. Stock has left and the order lines are updated.');
    }

    public function render(Packing $packing): View
    {
        $d = $this->delivery->load(['customer', 'lines.orderLine.order', 'lines.variant.item.stockUnit', 'lines.allocations.lot', 'packingList.cartons.lines.deliveryLine.variant']);
        $list = $d->packingList;

        return view('delivery::livewire.deliveries.show', [
            'packed' => $list ? $packing->packedByLine($list) : [],
            'totals' => $list ? $packing->totals($list) : null,
            'invoices' => InvoiceLine::query()->with('invoice')->whereIn('delivery_line_id', $d->lines->pluck('id'))->get()->pluck('invoice')->unique('id'),
            'returns' => DeliveryReturn::query()->whereIn('delivery_line_id', $d->lines->pluck('id'))->get(),
        ])->title($d->number);
    }
}
