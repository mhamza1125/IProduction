<?php

namespace App\Modules\Purchasing\Livewire\Receipts;

use App\Modules\Purchasing\Actions\PostGoodsReceipt;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\VendorBill;
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
    public GoodsReceipt $receipt;

    public function mount(GoodsReceipt $receipt): void
    {
        $this->receipt = $receipt;
    }

    protected function document(): Model&Document
    {
        return $this->receipt;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(PostGoodsReceipt::class)($this->receipt, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->receipt->refresh();
    }

    public function delete(): void
    {
        $this->authorize('goods_receipts.delete');
        abort_unless($this->receipt->isDraft(), 403);
        $orderId = $this->receipt->purchase_order_id;
        $this->receipt->lines()->delete();
        $this->receipt->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('purchasing.orders.show', $orderId, navigate: true);
    }

    public function render(): View
    {
        return view('purchasing::livewire.receipts.show', [
            'lines' => $this->receipt->lines()->with(['variant.item.stockUnit', 'orderLine.unit', 'location', 'stage', 'lot', 'rejectReason'])->get(),
            'bills' => VendorBill::query()->whereHas('lines.receiptLine', fn ($q) => $q->where('goods_receipt_id', $this->receipt->id))->get(),
            'returns' => PurchaseReturn::query()->where('goods_receipt_id', $this->receipt->id)->get(),
        ])->title($this->receipt->number);
    }
}
