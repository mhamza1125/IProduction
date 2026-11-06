<?php

namespace App\Modules\Sales\Livewire\Orders;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\People\Models\Customer;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?SalesOrder $order = null;

    public ?int $customer_id = null;

    public ?string $order_date = null;

    public ?string $due_date = null;

    public string $customer_po_number = '';

    public string $shipping_address = '';

    public string $payment_terms = '';

    public string $notes = '';

    public bool $sells_rejected_stock = false;

    /** @var list<array{id: int|null, item_variant_id: int|string|null, description: string, quantity: string, unit_price: string, tax_rate: string, due_date: string|null}> */
    public array $lines = [];

    public function mount(?SalesOrder $order = null): void
    {
        if ($order?->exists) {
            abort_unless($order->isDraft() || ($order->isOpen() && auth()->user()?->can('sales_orders.override')), 403, 'This order cannot be edited.');
            $this->order = $order;
            $this->customer_id = $order->customer_id;
            $this->order_date = $order->order_date->format('Y-m-d');
            $this->due_date = $order->due_date?->format('Y-m-d');
            $this->fill(array_map(fn ($v) => $v ?? '', $order->only(['customer_po_number', 'shipping_address', 'payment_terms', 'notes'])));
            $this->sells_rejected_stock = $order->sells_rejected_stock;
            $this->lines = $order->lines()->get()->map(fn ($l) => [
                'id' => $l->id, 'item_variant_id' => $l->item_variant_id, 'description' => (string) $l->description,
                'quantity' => Decimal::round($l->quantity, 4), 'unit_price' => Decimal::round($l->unit_price, 4),
                'tax_rate' => (string) $l->tax_rate, 'due_date' => $l->due_date?->format('Y-m-d'),
            ])->all();
        } else {
            $this->order_date = now()->format('Y-m-d');
            $this->addLine();
        }
    }

    public function updatedCustomerId(): void
    {
        $customer = Customer::query()->find($this->customer_id);
        $this->shipping_address = (string) ($customer->shipping_address ?? $customer->billing_address ?? '');
        $this->payment_terms = (string) $customer?->payment_terms;
    }

    public function addLine(): void
    {
        $this->lines[] = ['id' => null, 'item_variant_id' => null, 'description' => '', 'quantity' => '', 'unit_price' => '', 'tax_rate' => '', 'due_date' => null];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function updatedLines(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.item_variant_id') && $value) {
            $i = (int) explode('.', $key)[0];
            if ($this->lines[$i]['unit_price'] === '') {
                $this->lines[$i]['unit_price'] = (string) ItemVariant::query()->with('item')->find((int) $value)?->sellingPrice();
            }
        }
    }

    public function save(SalesOrders $orders): void
    {
        $this->authorize($this->order ? 'sales_orders.edit' : 'sales_orders.create');

        $data = $this->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:order_date'],
            'customer_po_number' => ['nullable', 'string', 'max:60'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'payment_terms' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sells_rejected_stock' => ['boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_variant_id' => ['required', Rule::exists('item_variants', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.due_date' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['customer_id' => 'customer', 'lines.*.item_variant_id' => 'item', 'lines.*.quantity' => 'quantity', 'lines.*.unit_price' => 'price']);

        foreach ($data['lines'] as $i => $line) {
            if (! ItemVariant::query()->with('item')->find($line['item_variant_id'])?->item->can_sell) {
                $this->addError("lines.$i.item_variant_id", 'This item is not marked "can be sold".');

                return;
            }
            $data['lines'][$i]['id'] = $this->lines[$i]['id'] ?? null;
        }

        try {
            $order = $orders->save($this->order, [
                'customer_id' => $data['customer_id'], 'order_date' => $data['order_date'], 'due_date' => $data['due_date'] ?: null,
                'customer_po_number' => $data['customer_po_number'] ?: null, 'shipping_address' => $data['shipping_address'] ?: null,
                'payment_terms' => $data['payment_terms'] ?: null, 'notes' => $data['notes'] ?: null, 'sells_rejected_stock' => $data['sells_rejected_stock'],
            ], $data['lines']);
        } catch (DocumentException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        session()->flash('toast', "{$order->number} saved.");
        $this->redirectRoute('sales.orders.show', $order, navigate: true);
    }

    public function render(): View
    {
        $customer = Customer::query()->find($this->customer_id);

        return view('sales::livewire.orders.form', [
            'customers' => Customer::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->label().' ('.$c->currency_code.')']),
            'variants' => ItemVariant::query()->active()->whereHas('item', fn ($i) => $i->where('can_sell', true))->with('item')->get()->sortBy('sku')
                ->mapWithKeys(fn ($v) => [$v->id => $v->fullLabel()]),
            'currency' => $customer->currency_code ?? '',
            'total' => Decimal::sum(array_map(fn ($l) => is_numeric($l['quantity']) && is_numeric($l['unit_price']) ? Decimal::mul($l['quantity'], $l['unit_price']) : '0', $this->lines)),
        ])->title($this->order ? 'Edit '.$this->order->number : 'New sales order');
    }
}
