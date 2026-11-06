<?php

namespace App\Modules\Purchasing\Livewire\Orders;

use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\VendorBill;
use App\Modules\Purchasing\Services\PurchaseOrders;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public PurchaseOrder $order;

    public string $reason = '';

    public function mount(PurchaseOrder $order): void
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
        $this->authorize('purchase_orders.approve');
        $this->run(fn () => $workflow->approve($this->order), 'Approved.');
    }

    public function returnToDraft(DocumentWorkflow $workflow): void
    {
        $this->authorize('purchase_orders.approve');
        $this->run(fn () => $workflow->returnToDraft($this->order, $this->reason), 'Returned to draft.');
    }

    public function markSent(PurchaseOrders $orders): void
    {
        $this->authorize('purchase_orders.print');
        $this->run(fn () => $orders->markSent($this->order), 'Marked as sent.');
    }

    public function cancel(PurchaseOrders $orders): void
    {
        $this->authorize('purchase_orders.cancel');
        $this->run(fn () => $orders->cancel($this->order, $this->reason), 'Cancelled.');
    }

    public function closeShort(PurchaseOrders $orders): void
    {
        $this->authorize('purchase_orders.cancel');
        $this->run(fn () => $orders->closeShort($this->order, $this->reason), 'Remaining quantity closed short.');
    }

    public function delete(): void
    {
        $this->authorize('purchase_orders.delete');
        abort_unless($this->order->isDraft(), 403);
        $this->order->lines()->delete();
        $this->order->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('purchasing.orders.index', navigate: true);
    }

    public function render(): View
    {
        $receiptIds = $this->order->receipts()->pluck('id');

        return view('purchasing::livewire.orders.show', [
            'lines' => $this->order->lines()->with(['variant.item', 'unit', 'stage'])->get(),
            'receipts' => GoodsReceipt::query()->whereIn('id', $receiptIds)->orderBy('id')->get(),
            'bills' => VendorBill::query()->whereHas('lines.receiptLine', fn ($q) => $q->whereIn('goods_receipt_id', $receiptIds))->get(),
            'returns' => PurchaseReturn::query()->whereIn('goods_receipt_id', $receiptIds)->get(),
        ])->title($this->order->number);
    }
}
