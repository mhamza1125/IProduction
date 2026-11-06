<?php

namespace App\Modules\Purchasing\Livewire\Bills;

use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Purchasing\Actions\PostVendorBill;
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
    public VendorBill $bill;

    public function mount(VendorBill $bill): void
    {
        $this->bill = $bill;
    }

    protected function document(): Model&Document
    {
        return $this->bill;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(PostVendorBill::class)($this->bill, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->bill->refresh();
    }

    public function delete(): void
    {
        $this->authorize('vendor_bills.delete');
        abort_unless($this->bill->isDraft(), 403);
        $this->bill->lines()->delete();
        $this->bill->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('purchasing.bills.index', navigate: true);
    }

    public function render(): View
    {
        return view('purchasing::livewire.bills.show', [
            'lines' => $this->bill->lines()->with(['variant.item', 'receiptLine.receipt'])->get(),
            'payments' => PaymentAllocation::query()->where('allocatable_type', $this->bill->getMorphClass())->where('allocatable_id', $this->bill->id)->with('payment')->get(),
        ])->title($this->bill->number);
    }
}
