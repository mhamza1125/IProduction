<?php

namespace App\Modules\Delivery\Livewire\CreditNotes;

use App\Modules\Delivery\Models\CreditNote;
use App\Modules\Delivery\Models\DeliveryReturn;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\CreditNotes;
use App\Modules\People\Models\Customer;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?CreditNote $note = null;

    public ?int $customer_id = null;

    #[Url(as: 'invoice', except: null)]
    public ?int $invoice_id = null;

    #[Url(as: 'return', except: null)]
    public ?int $delivery_return_id = null;

    public string $credit_date = '';

    public string $reason = '';

    public string $notes = '';

    /** @var list<array{description: string, amount: string, tax_rate: string}> */
    public array $lines = [];

    public function mount(?CreditNote $note = null): void
    {
        if ($note?->exists) {
            abort_unless($note->isDraft(), 403, 'Only drafts can be edited.');
            $this->note = $note;
            $this->fill($note->only(['customer_id', 'invoice_id', 'delivery_return_id', 'reason']));
            $this->credit_date = $note->credit_date->format('Y-m-d');
            $this->notes = (string) $note->notes;
            $this->lines = $note->lines->map(fn ($l) => ['description' => $l->description, 'amount' => (string) $l->amount, 'tax_rate' => (string) $l->tax_rate])->all();

            return;
        }
        $this->credit_date = now()->format('Y-m-d');
        if ($this->invoice_id && ($inv = Invoice::query()->find($this->invoice_id))) {
            $this->customer_id = $inv->customer_id;
        }
        if ($this->delivery_return_id && ($ret = DeliveryReturn::query()->with(['deliveryLine.variant', 'invoiceLine'])->find($this->delivery_return_id))) {
            $this->customer_id = $ret->customer_id;
            $this->invoice_id ??= $ret->invoiceLine?->invoice_id;
            $this->reason = "Return {$ret->number}: {$ret->reason}";
            $price = $ret->invoiceLine->unit_price ?? null;
            $this->lines[] = ['description' => "{$ret->deliveryLine->variant->sku} returned × ".Decimal::round($ret->quantity, 4), 'amount' => $price ? Decimal::mul($ret->quantity, $price) : '', 'tax_rate' => (string) ($ret->invoiceLine->tax_rate ?? '')];
        }
        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['description' => '', 'amount' => '', 'tax_rate' => ''];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function save(CreditNotes $notes): void
    {
        $this->authorize($this->note ? 'credit_notes.edit' : 'credit_notes.create');
        $this->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'credit_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        try {
            $note = $notes->save($this->note, ['customer_id' => $this->customer_id, 'invoice_id' => $this->invoice_id, 'delivery_return_id' => $this->delivery_return_id,
                'credit_date' => $this->credit_date, 'reason' => $this->reason, 'notes' => $this->notes], $this->lines);
        } catch (DocumentException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$note->number} saved.");
        $this->redirectRoute('delivery.credit-notes.show', $note, navigate: true);
    }

    public function render(): View
    {
        return view('delivery::livewire.credit-notes.form', [
            'customers' => Customer::query()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->label().' ('.$c->currency_code.')']),
            'invoices' => $this->customer_id ? Invoice::query()->where('customer_id', $this->customer_id)->whereNotNull('posted_at')->latest('invoice_date')->limit(100)->get()
                ->mapWithKeys(fn ($i) => [$i->id => $i->number.' · balance '.$i->balance().' '.$i->currency_code]) : collect(),
        ])->title($this->note ? 'Edit '.$this->note->number : 'New credit note');
    }
}
