<?php

namespace App\Modules\Purchasing\Livewire\Orders;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\PurchaseOrders;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?PurchaseOrder $order = null;

    public ?int $vendor_id = null;

    public ?string $order_date = null;

    public ?string $expected_date = null;

    public string $payment_terms = '';

    public string $notes = '';

    /** @var list<array{item_variant_id: int|string|null, stage_id: int|string|null, description: string, quantity: string, unit_id: int|string|null, rate: string, expected_date: string|null}> */
    public array $lines = [];

    public function mount(?PurchaseOrder $order = null): void
    {
        if ($order?->exists) {
            abort_unless($order->isDraft(), 403, 'Only drafts can be edited.');
            $this->order = $order;
            $this->vendor_id = $order->vendor_id;
            $this->order_date = $order->order_date->format('Y-m-d');
            $this->expected_date = $order->expected_date?->format('Y-m-d');
            $this->payment_terms = (string) $order->payment_terms;
            $this->notes = (string) $order->notes;
            $this->lines = $order->lines()->get()->map(fn ($l) => [
                'item_variant_id' => $l->item_variant_id, 'stage_id' => $l->stage_id, 'description' => (string) $l->description,
                'quantity' => Decimal::round($l->quantity, 4), 'unit_id' => $l->unit_id, 'rate' => Decimal::round($l->rate, 4),
                'expected_date' => $l->expected_date?->format('Y-m-d'),
            ])->all();
        } else {
            $this->order_date = now()->format('Y-m-d');
            $this->addLine();
        }
    }

    public function updatedVendorId(): void
    {
        $this->payment_terms = (string) Vendor::query()->whereKey($this->vendor_id)->value('payment_terms');
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_variant_id' => null, 'stage_id' => null, 'description' => '', 'quantity' => '', 'unit_id' => null, 'rate' => '', 'expected_date' => null];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    /** Defaults the unit (purchase unit) and rate (purchase cost) when an item is picked. */
    public function updatedLines(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.item_variant_id') && $value) {
            $i = (int) explode('.', $key)[0];
            $variant = ItemVariant::query()->with('item')->find((int) $value);
            $this->lines[$i]['unit_id'] = $variant?->item->purchase_unit_id ?? $variant?->item->stock_unit_id;
            if ($this->lines[$i]['rate'] === '' && auth()->user()?->can('cost.view')) {
                $this->lines[$i]['rate'] = (string) $variant?->purchaseCost();
            }
        }
    }

    public function save(PurchaseOrders $orders): void
    {
        $this->authorize($this->order ? 'purchase_orders.edit' : 'purchase_orders.create');

        $data = $this->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'expected_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:order_date'],
            'payment_terms' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_variant_id' => ['required', Rule::exists('item_variants', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'lines.*.stage_id' => ['nullable', Rule::exists('stages', 'id')],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'lines.*.unit_id' => ['required', Rule::exists('units', 'id')],
            'lines.*.rate' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.expected_date' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['vendor_id' => 'vendor', 'lines.*.item_variant_id' => 'item', 'lines.*.quantity' => 'quantity', 'lines.*.rate' => 'rate']);

        foreach ($data['lines'] as $i => $line) {
            $variant = ItemVariant::query()->with('item')->find($line['item_variant_id']);
            if (! $variant?->item->can_buy) {
                $this->addError("lines.$i.item_variant_id", 'This item is not marked "can be bought".');

                return;
            }
        }

        try {
            $order = $orders->saveDraft($this->order, [
                'vendor_id' => $data['vendor_id'],
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?: null,
                'payment_terms' => $data['payment_terms'] ?: null,
                'notes' => $data['notes'] ?: null,
            ], $data['lines']);
        } catch (DocumentException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        session()->flash('toast', "{$order->number} saved.");
        $this->redirectRoute('purchasing.orders.show', $order, navigate: true);
    }

    public function render(): View
    {
        $total = Decimal::sum(array_map(fn ($l) => is_numeric($l['quantity']) && is_numeric($l['rate']) ? Decimal::mul($l['quantity'], $l['rate']) : '0', $this->lines));

        return view('purchasing::livewire.orders.form', [
            'vendors' => Vendor::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($v) => [$v->id => $v->label()]),
            'variants' => ItemVariant::query()->active()->whereHas('item', fn ($i) => $i->where('can_buy', true))->with('item')->get()
                ->sortBy('sku')->mapWithKeys(fn ($v) => [$v->id => $v->fullLabel()]),
            'units' => Unit::query()->active()->orderBy('name')->pluck('symbol', 'id'),
            'stages' => Stage::query()->ordered()->pluck('name', 'id'),
            'total' => $total,
        ])->title($this->order ? 'Edit '.$this->order->number : 'New purchase order');
    }
}
