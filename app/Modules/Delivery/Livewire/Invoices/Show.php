<?php

namespace App\Modules\Delivery\Livewire\Invoices;

use App\Modules\Delivery\Models\CreditNote;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\Invoices;
use App\Support\Documents\Document;
use App\Support\Documents\DocumentException;
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
    public Invoice $invoice;

    public ?int $credit_note_id = null;

    public string $credit_amount = '';

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    protected function document(): Model&Document
    {
        return $this->invoice;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(Invoices::class)->post($this->invoice, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->invoice->refresh();
    }

    public function delete(): void
    {
        $this->authorize('invoices.delete');
        abort_unless($this->invoice->isDraft(), 403);
        $this->invoice->lines()->delete();
        $this->invoice->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('delivery.invoices.index', navigate: true);
    }

    public function applyCredit(Invoices $invoices): void
    {
        $this->authorize('credit_notes.post');
        $this->validate(['credit_note_id' => ['required', 'exists:credit_notes,id'], 'credit_amount' => ['required', 'numeric', 'gt:0']]);
        $note = CreditNote::query()->where('customer_id', $this->invoice->customer_id)->whereNotNull('posted_at')->findOrFail($this->credit_note_id);
        try {
            $invoices->allocate($this->invoice, $note, (string) $this->credit_amount);
        } catch (DocumentException $e) {
            $this->addError('credit_amount', $e->getMessage());

            return;
        }
        $this->reset('credit_note_id', 'credit_amount');
        $this->dispatch('toast', message: 'Credit applied.');
    }

    public function render(): View
    {
        $inv = $this->invoice->load(['customer', 'lines.variant.item', 'lines.orderLine.order', 'lines.deliveryLine.delivery', 'allocations.source']);
        $credits = $inv->isPosted() ? CreditNote::query()->where('customer_id', $inv->customer_id)->where('currency_code', $inv->currency_code)->whereNotNull('posted_at')->get()
            ->filter(fn (CreditNote $c) => (float) $c->unallocated() > 0) : collect();

        return view('delivery::livewire.invoices.show', [
            'credits' => $credits->mapWithKeys(fn (CreditNote $c) => [$c->id => $c->number.' · '.$c->unallocated().' '.$c->currency_code.' left']),
        ])->title($inv->number);
    }
}
