<?php

namespace App\Modules\Delivery\Livewire\Deliveries;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Services\Deliveries;
use App\Modules\People\Models\Customer;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Step 1–2: choose a customer, then take quantities from any of their open order lines.
 */
class Form extends Component
{
    #[Locked]
    public ?Delivery $delivery = null;

    #[Url(as: 'customer', except: null)]
    public ?int $customer_id = null;

    public string $delivery_date = '';

    public string $shipping_address = '';

    public string $shipment_method = '';

    public string $vehicle = '';

    public string $tracking_number = '';

    public string $notes = '';

    /** @var array<int, string> sales order line id => quantity */
    public array $quantities = [];

    /** @var array<int, string> sales order line id => reason for delivering more than ordered */
    public array $overReasons = [];

    public function mount(?Delivery $delivery = null): void
    {
        if ($delivery?->exists) {
            abort_unless($delivery->isDraft(), 403, 'Only drafts can be edited.');
            $this->delivery = $delivery;
            $this->customer_id = $delivery->customer_id;
            $this->delivery_date = $delivery->delivery_date->format('Y-m-d');
            foreach (['shipping_address', 'shipment_method', 'vehicle', 'tracking_number', 'notes'] as $f) {
                $this->{$f} = (string) $delivery->{$f};
            }
            foreach ($delivery->lines as $l) {
                $this->quantities[$l->sales_order_line_id] = Decimal::round($l->quantity, 4);
                $this->overReasons[$l->sales_order_line_id] = (string) $l->over_reason;
            }

            return;
        }
        $this->delivery_date = now()->format('Y-m-d');
    }

    public function updatedCustomerId(): void
    {
        $this->quantities = [];
        $this->shipping_address = (string) Customer::query()->whereKey($this->customer_id)->value('shipping_address');
    }

    public function fillRemaining(Deliveries $deliveries): void
    {
        $customer = Customer::query()->findOrFail($this->customer_id);
        foreach ($deliveries->openLines($customer, $this->delivery?->id) as $row) {
            $ready = Decimal::compare($row['ready'], $row['remaining']) < 0 ? $row['ready'] : $row['remaining'];
            if (Decimal::compare($ready, '0') > 0) {
                $this->quantities[$row['line']->id] = Decimal::round($ready, 4);
            }
        }
    }

    public function save(Deliveries $deliveries): void
    {
        $this->authorize($this->delivery ? 'deliveries.edit' : 'deliveries.create');
        $this->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'delivery_date' => ['required', 'date'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
            'shipment_method' => ['nullable', 'string', 'max:50'],
            'vehicle' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);
        $lines = [];
        foreach ($this->quantities as $lineId => $qty) {
            if ($qty !== '' && (float) $qty > 0) {
                $lines[] = ['sales_order_line_id' => (int) $lineId, 'quantity' => (string) $qty, 'over_reason' => $this->overReasons[$lineId] ?? null];
            }
        }
        try {
            $delivery = $deliveries->save($this->delivery, [
                'customer_id' => $this->customer_id, 'delivery_date' => $this->delivery_date, 'shipping_address' => $this->shipping_address,
                'shipment_method' => $this->shipment_method, 'vehicle' => $this->vehicle, 'tracking_number' => $this->tracking_number, 'notes' => $this->notes,
            ], $lines);
        } catch (DocumentException $e) {
            $this->addError('quantities', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$delivery->number} saved.");
        $this->redirectRoute('delivery.deliveries.show', $delivery, navigate: true);
    }

    public function render(Deliveries $deliveries): View
    {
        $customer = $this->customer_id ? Customer::query()->find($this->customer_id) : null;

        return view('delivery::livewire.deliveries.form', [
            'customers' => Customer::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->label().' ('.$c->currency_code.')']),
            'rows' => $customer ? $deliveries->openLines($customer, $this->delivery?->id) : collect(),
            'customer' => $customer,
        ])->title($this->delivery ? 'Edit '.$this->delivery->number : 'New delivery');
    }
}
