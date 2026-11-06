<?php

namespace App\Modules\Sales\Livewire\Orders;

use App\Modules\Inventory\Models\Reservation;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Models\SupplyAllocation;
use App\Modules\Sales\Services\Planning;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public SalesOrder $order;

    public string $reason = '';

    public function mount(SalesOrder $order): void
    {
        $this->order = $order;
    }

    private function run(callable $action, string $message): void
    {
        try {
            $action();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }
        $this->reason = '';
        $this->order->refresh();
        $this->dispatch('toast', message: $message);
    }

    public function approve(DocumentWorkflow $workflow): void
    {
        $this->authorize('sales_orders.approve');
        $this->run(fn () => $workflow->approve($this->order), 'Approved.');
    }

    public function returnToDraft(DocumentWorkflow $workflow): void
    {
        $this->authorize('sales_orders.approve');
        $this->run(fn () => $workflow->returnToDraft($this->order, $this->reason), 'Returned to draft.');
    }

    public function cancel(SalesOrders $orders): void
    {
        $this->authorize('sales_orders.cancel');
        $this->run(fn () => $orders->cancel($this->order, $this->reason), 'Cancelled. Reservations released.');
    }

    public function close(SalesOrders $orders): void
    {
        $this->authorize('sales_orders.cancel');
        $this->run(fn () => $orders->close($this->order, $this->reason), 'Remaining quantity closed. Reservations released.');
    }

    public function delete(): void
    {
        $this->authorize('sales_orders.delete');
        abort_unless($this->order->isDraft(), 403);
        $this->order->lines()->delete();
        $this->order->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('sales.orders.index', navigate: true);
    }

    public function render(Planning $planning): View
    {
        $lines = $this->order->lines()->with(['variant.item.stockUnit'])->get();
        $pictures = $this->order->isOpen() ? $lines->mapWithKeys(fn ($l) => [$l->id => $planning->picture($l)]) : collect();

        return view('sales::livewire.orders.show', [
            'lines' => $lines,
            'pictures' => $pictures,
            'reserved' => Reservation::query()->where('demand_type', (new SalesOrderLine)->getMorphClass())
                ->whereIn('demand_id', $lines->pluck('id'))->where('status', 'active')
                ->get()->groupBy('demand_id')->map(fn ($rs) => Decimal::sum($rs->map->open(), 4)),
            'linked' => SupplyAllocation::query()->whereIn('sales_order_line_id', $lines->pluck('id'))->with('supply')->get(),
        ])->title($this->order->number);
    }
}
