<?php

namespace App\Modules\Delivery\Livewire\Invoices;

use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\Invoices;
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
    public ?Invoice $invoice = null;

    #[Url(as: 'customer', except: null)]
    public ?int $customer_id = null;

    public string $invoice_date = '';

    public ?string $due_date = null;

    public string $payment_terms = '';

    public string $notes = '';

    /** @var array<int, array{selected: bool, unit_price: string, discount: string, tax_rate: string}> delivery line id => line */
    public array $lines = [];

    public function mount(?Invoice $invoice = null): void
    {
        if ($invoice?->exists) {
            abort_unless($invoice->isDraft(), 403, 'Only drafts can be edited.');
            $this->invoice = $invoice;
            $this->customer_id = $invoice->customer_id;
            $this->invoice_date = $invoice->invoice_date->format('Y-m-d');
            $this->due_date = $invoice->due_date?->format('Y-m-d');
            $this->payment_terms = (string) $invoice->payment_terms;
            $this->notes = (string) $invoice->notes;
            foreach ($invoice->lines as $l) {
                $this->lines[$l->delivery_line_id] = ['selected' => true, 'unit_price' => Decimal::round($l->unit_price, 4), 'discount' => (string) $l->discount, 'tax_rate' => (string) $l->tax_rate];
            }

            return;
        }
        $this->invoice_date = now()->format('Y-m-d');
        $this->payment_terms = (string) Customer::query()->whereKey($this->customer_id)->value('payment_terms');
    }

    public function updatedCustomerId(): void
    {
        $this->lines = [];
        $this->payment_terms = (string) Customer::query()->whereKey($this->customer_id)->value('payment_terms');
    }

    public function save(Invoices $invoices): void
    {
        $this->authorize($this->invoice ? 'invoices.edit' : 'invoices.create');
        $this->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $chosen = [];
        foreach ($this->lines as $id => $l) {
            if ($l['selected']) {
                $chosen[] = ['delivery_line_id' => (int) $id, 'unit_price' => $l['unit_price'], 'discount' => $l['discount'], 'tax_rate' => $l['tax_rate']];
            }
        }
        try {
            $invoice = $invoices->save($this->invoice, ['customer_id' => $this->customer_id, 'invoice_date' => $this->invoice_date, 'due_date' => $this->due_date, 'payment_terms' => $this->payment_terms, 'notes' => $this->notes], $chosen);
        } catch (DocumentException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$invoice->number} saved.");
        $this->redirectRoute('delivery.invoices.show', $invoice, navigate: true);
    }

    public function render(Invoices $invoices): View
    {
        $customer = $this->customer_id ? Customer::query()->find($this->customer_id) : null;
        $available = $customer ? $invoices->uninvoiced($customer, $this->invoice?->id) : collect();
        foreach ($available as $dl) {
            $this->lines[$dl->id] ??= ['selected' => false, 'unit_price' => Decimal::round($dl->orderLine->unit_price, 4), 'discount' => '', 'tax_rate' => $dl->orderLine->tax_rate === null ? '' : (string) $dl->orderLine->tax_rate];
        }

        return view('delivery::livewire.invoices.form', [
            'customers' => Customer::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->label().' ('.$c->currency_code.')']),
            'available' => $available,
            'canChangePrice' => auth()->user()->can('invoices.override'),
        ])->title($this->invoice ? 'Edit '.$this->invoice->number : 'New invoice');
    }
}
