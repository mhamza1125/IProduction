<?php

namespace App\Modules\Sales\Livewire\Planning;

use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Vendor;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Services\Planning;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every open order line with what blocks it and the next action; selecting a
 * line opens the planning steps for it.
 */
#[Title('Planning')]
class Index extends Component
{
    #[Url(except: '')]
    public string $customer = '';

    #[Url(as: 'order', except: null)]
    public ?int $orderId = null;

    #[Url(as: 'line', except: null)]
    #[Locked]
    public ?int $lineId = null;

    public string $buy = '0';

    public string $make = '0';

    public string $contractor = '0';

    public ?int $route_id = null;

    /** @var array<int, array{order: bool, quantity: string, vendor_id: int|string|null, rate: string}> variant id => purchase row */
    public array $purchase = [];

    public function select(int $id): void
    {
        $line = SalesOrderLine::query()->findOrFail($id);
        $this->lineId = $line->id;
        $this->buy = Decimal::round($line->plan_buy, 4);
        $this->make = Decimal::round($line->plan_make, 4);
        $this->contractor = Decimal::round($line->plan_contractor, 4);
        $this->route_id = $line->route_id ?? ProductionRoute::query()->where('item_id', $line->variant->item_id)->where('is_default', true)->value('id');
        $this->purchase = [];
        $this->resetValidation();
    }

    private function line(): ?SalesOrderLine
    {
        return $this->lineId ? SalesOrderLine::query()->with(['order.customer', 'variant.item'])->find($this->lineId) : null;
    }

    public function reserve(Planning $planning): void
    {
        $this->authorize('reservations.create');
        $this->attempt(fn () => 'Reserved '.$planning->reserveFree($this->line() ?? abort(404)).'.');
    }

    public function savePlan(Planning $planning): void
    {
        $this->authorize('planning.create');
        $this->validate([
            'buy' => ['required', 'numeric', 'min:0'], 'make' => ['required', 'numeric', 'min:0'], 'contractor' => ['required', 'numeric', 'min:0'],
            'route_id' => ['nullable', 'exists:production_routes,id'],
        ]);
        $this->attempt(function () use ($planning) {
            $planning->savePlan($this->line() ?? abort(404), (string) $this->buy, (string) $this->make, (string) $this->contractor, $this->route_id);

            return 'Plan saved.';
        });
    }

    public function createPurchaseOrders(Planning $planning): void
    {
        $this->authorize('purchase_orders.create');
        $this->validate([
            'purchase.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'purchase.*.rate' => ['nullable', 'numeric', 'min:0'],
            'purchase.*.vendor_id' => ['nullable', 'exists:vendors,id'],
        ]);

        $rows = [];
        foreach ($this->purchase as $variantId => $row) {
            if (! $row['order']) {
                continue;
            }
            if (empty($row['vendor_id'])) {
                $this->addError("purchase.$variantId.vendor_id", 'Choose a vendor.');

                return;
            }
            $rows[] = ['item_variant_id' => (int) $variantId, 'quantity' => (string) $row['quantity'], 'vendor_id' => (int) $row['vendor_id'], 'rate' => (string) ($row['rate'] ?: '0')];
        }

        $this->attempt(function () use ($planning, $rows) {
            $pos = $planning->createPurchaseOrders($this->line() ?? abort(404), $rows);
            $this->purchase = [];

            return 'Draft purchase orders created: '.collect($pos)->pluck('number')->implode(', ').'.';
        });
    }

    private function attempt(callable $action): void
    {
        try {
            $message = $action();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }
        $this->dispatch('toast', message: $message);
    }

    public function render(Planning $planning): View
    {
        $rows = $planning->openLines($this->customer !== '' ? (int) $this->customer : null)
            ->when($this->orderId, fn ($c) => $c->filter(fn ($r) => $r['line']->sales_order_id === $this->orderId))->values();

        $line = $this->line();
        $picture = $line ? $planning->picture($line) : null;
        $requirements = [];
        $requirementsError = null;

        if ($line) {
            try {
                $requirements = $planning->requirements($line);
            } catch (DocumentException $e) {
                $requirementsError = $e->getMessage();
            }

            // Purchase rows: finished goods to buy, plus raw/semi-finished shortages that are bought.
            $suggest = [];
            if (Decimal::compare($line->plan_buy, '0') > 0) {
                $suggest[$line->item_variant_id] = [$line->variant, $line->plan_buy];
            }
            foreach ($requirements as $r) {
                if (Decimal::compare($r['shortage'], '0') > 0 && ! $r['made'] && $r['variant']->item->can_buy) {
                    $suggest[$r['variant']->id] = [$r['variant'], Decimal::add($suggest[$r['variant']->id][1] ?? '0', $r['shortage'], 4)];
                }
            }
            foreach ($suggest as $id => [$variant, $qty]) {
                $this->purchase[$id] ??= ['order' => true, 'quantity' => $qty, 'vendor_id' => null, 'rate' => auth()->user()?->can('cost.view') ? (string) $variant->purchaseCost() : ''];
            }
            $purchaseLabels = collect($suggest)->map(fn ($s) => $s[0]->fullLabel());
        }

        return view('sales::livewire.planning.index', [
            'rows' => $rows,
            'line' => $line,
            'picture' => $picture,
            'requirements' => $requirements,
            'requirementsError' => $requirementsError,
            'purchaseLabels' => $purchaseLabels ?? collect(),
            'routes' => $line ? ProductionRoute::query()->where('item_id', $line->variant->item_id)->where('is_active', true)->pluck('name', 'id') : collect(),
            'vendors' => Vendor::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($v) => [$v->id => $v->label()]),
            'customers' => Customer::query()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
