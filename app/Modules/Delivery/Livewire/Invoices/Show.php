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

    public bool $showShipping = false;

    /** @var array<string, string|null> */
    public array $shipping = [];

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

    public function editShipping(): void
    {
        $this->authorize('invoices.edit');
        $this->resetValidation();
        $this->shipping = [];
        foreach (Invoice::SHIPPING_FIELDS as $field) {
            $value = $this->invoice->getAttribute($field);
            $this->shipping[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : ($value === null ? '' : (string) $value);
        }
        $this->showShipping = true;
    }

    public function saveShipping(Invoices $invoices): void
    {
        $this->authorize('invoices.edit');
        $this->validate([
            'shipping.incoterm' => ['nullable', 'in:'.implode(',', array_keys(Invoice::INCOTERMS))],
            'shipping.incoterm_place' => ['nullable', 'string', 'max:100'], 'shipping.port_of_loading' => ['nullable', 'string', 'max:100'],
            'shipping.port_of_discharge' => ['nullable', 'string', 'max:100'], 'shipping.final_destination' => ['nullable', 'string', 'max:100'],
            'shipping.vessel_flight' => ['nullable', 'string', 'max:100'], 'shipping.container_no' => ['nullable', 'string', 'max:100'],
            'shipping.bl_awb_no' => ['nullable', 'string', 'max:60'], 'shipping.bl_awb_date' => ['nullable', 'date'],
            'shipping.fi_number' => ['nullable', 'string', 'max:60'], 'shipping.gd_number' => ['nullable', 'string', 'max:60'], 'shipping.gd_date' => ['nullable', 'date'],
            'shipping.consignee' => ['nullable', 'string', 'max:1000'], 'shipping.notify_party' => ['nullable', 'string', 'max:1000'], 'shipping.shipping_marks' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $invoices->updateShipping($this->invoice, $this->shipping);
        } catch (DocumentException $e) {
            $this->addError('shipping.incoterm', $e->getMessage());

            return;
        }
        $this->showShipping = false;
        $this->invoice->refresh();
        $this->dispatch('toast', message: 'Shipping details saved.');
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
