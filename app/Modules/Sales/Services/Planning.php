<?php

namespace App\Modules\Sales\Services;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Services\BomExplosion;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Services\PurchaseOrders;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Models\SupplyAllocation;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Planning (specification, Module 5):
 *  1. reserve free stock; 2. the rest is the shortfall; 3. split it into buy /
 *  make / contractor; 4. pick a route for make; 5. explode the BOM against
 *  stock (finished goods first, then semi-finished components, then raw
 *  materials; rejected/returned stock and other orders' reservations never
 *  count; open POs count as incoming, not as stock); 6. create purchase orders
 *  for shortages grouped by vendor, linked to the order line; 7. PTCs and
 *  contractor jobs come from the Production module.
 */
class Planning
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly BomExplosion $explosion,
        private readonly PurchaseOrders $orders,
        private readonly SalesOrders $salesOrders,
    ) {}

    /**
     * Open order lines with their supply picture.
     *
     * @return Collection<int, array{line: SalesOrderLine, required: string, reserved: string, free: string, on_order: string, in_production: string, shortfall: string, blocker: string, next: string}>
     */
    public function openLines(?int $customerId = null): Collection
    {
        $lines = SalesOrderLine::query()
            ->with(['order.customer', 'variant.item'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', SalesOrder::OPEN)->where('sells_rejected_stock', false)
                ->when($customerId, fn ($q) => $q->where('customer_id', $customerId)))
            ->get()
            ->filter(fn (SalesOrderLine $l) => Decimal::compare($l->remaining(), '0') > 0)
            ->sortBy(fn (SalesOrderLine $l) => ($l->due_date ?? $l->order->due_date ?? $l->order->order_date)->format('Y-m-d').'-'.$l->id)
            ->values();

        return $lines->map(fn (SalesOrderLine $l) => $this->picture($l));
    }

    /**
     * @return array{line: SalesOrderLine, required: string, reserved: string, free: string, on_order: string, in_production: string, shortfall: string, blocker: string, next: string}
     */
    public function picture(SalesOrderLine $line): array
    {
        $required = $line->remaining();
        $reserved = Decimal::sum($line->reservations()->where('status', Reservation::ACTIVE)->get()->map(fn (Reservation $r) => $r->open()), 4);
        $poType = (new PurchaseOrderLine)->getMorphClass();
        $allocations = $line->allocations()->where('status', 'open')->where('item_variant_id', $line->item_variant_id)->get();
        $onOrder = Decimal::sum($allocations->where('supply_type', $poType)->map(fn ($a) => $a->open()), 4);
        $inProduction = Decimal::sum($allocations->where('supply_type', '!=', $poType)->map(fn ($a) => $a->open()), 4);
        $free = $this->stock->freeQuantity($line->item_variant_id);

        $covered = Decimal::add(Decimal::add($reserved, $onOrder, 4), $inProduction, 4);
        $shortfall = Decimal::sub($required, $covered, 4);
        $shortfall = Decimal::isNegative($shortfall) ? '0.0000' : $shortfall;
        $planned = Decimal::add(Decimal::add($line->plan_buy, $line->plan_make, 4), $line->plan_contractor, 4);

        [$blocker, $next] = match (true) {
            Decimal::isZero($shortfall) && Decimal::compare($reserved, $required) >= 0 => ['Ready to deliver', 'Create a delivery'],
            Decimal::isZero($shortfall) => ['Waiting for supply', 'Receive the purchase or finish production'],
            Decimal::compare($free, '0') > 0 => ["{$free} free in stock", 'Reserve free stock'],
            Decimal::isZero($planned) => ["Short by {$shortfall}", 'Split into buy / make / contractor'],
            default => ["Short by {$shortfall}", 'Create purchase orders or production'],
        };

        return compact('line', 'required', 'reserved', 'free', 'shortfall', 'blocker', 'next') + ['on_order' => $onOrder, 'in_production' => $inProduction];
    }

    /**
     * Step 1: reserve as much free stock as the line still needs.
     */
    public function reserveFree(SalesOrderLine $line): string
    {
        return DB::transaction(function () use ($line) {
            $this->stock->lockVariant($line->item_variant_id);
            $picture = $this->picture($line);
            $want = Decimal::sub($picture['required'], $picture['reserved'], 4);
            $qty = Decimal::compare($picture['free'], $want) < 0 ? $picture['free'] : $want;

            if (Decimal::compare($qty, '0') <= 0) {
                throw new DocumentException('There is no free stock to reserve for this line.');
            }

            $this->stock->reserve($line, $line->item_variant_id, $qty);
            $this->salesOrders->refreshStatus($line->order);

            return $qty;
        });
    }

    /**
     * Step 3–4: how the uncovered quantity will be supplied.
     */
    public function savePlan(SalesOrderLine $line, string $buy, string $make, string $contractor, ?int $routeId): void
    {
        $picture = $this->picture($line);
        $uncovered = Decimal::sub($picture['required'], $picture['reserved'], 4);
        $sum = Decimal::add(Decimal::add($buy, $make, 4), $contractor, 4);

        if (Decimal::compare($sum, $uncovered) > 0) {
            throw new DocumentException("Buy + make + contractor ({$sum}) is more than the {$uncovered} not covered by stock.");
        }

        $item = $line->variant->item;
        if (Decimal::compare($buy, '0') > 0 && ! $item->can_buy) {
            throw new DocumentException("{$item->code} cannot be bought.");
        }
        if (Decimal::compare(Decimal::add($make, $contractor, 4), '0') > 0 && ! $item->can_make) {
            throw new DocumentException("{$item->code} cannot be made.");
        }
        if (Decimal::compare($make, '0') > 0 && $routeId === null) {
            throw new DocumentException('Choose the route for the quantity to make.');
        }

        $line->update(['plan_buy' => $buy, 'plan_make' => $make, 'plan_contractor' => $contractor, 'route_id' => $routeId]);
        $this->salesOrders->refreshStatus($line->order);
    }

    /**
     * Step 5: component requirements for the quantity to make (and contractor,
     * when the company supplies the materials).
     *
     * @return list<array{level: int, variant: ItemVariant, required: string, free: string, incoming: string, shortage: string, made: bool}>
     */
    public function requirements(SalesOrderLine $line, bool $includeContractor = true): array
    {
        $qty = $includeContractor ? Decimal::add($line->plan_make, $line->plan_contractor, 4) : $line->plan_make;

        if (Decimal::compare($qty, '0') <= 0) {
            return [];
        }

        $poType = (new PurchaseOrderLine)->getMorphClass();
        $incoming = $line->allocations()->where('status', 'open')->where('supply_type', $poType)->get()
            ->groupBy('item_variant_id')->map(fn ($rows) => Decimal::sum($rows->map(fn ($a) => $a->open()), 4));

        /** @var array<int, BigDecimal> $used free stock already counted in this explosion */
        $used = [];
        $rows = [];
        $this->walk($line->variant, $qty, 1, $incoming, $used, $rows);

        return $rows;
    }

    /**
     * @param  Collection<int, string>  $incoming
     * @param  array<int, BigDecimal>  $used
     * @param  list<array{level: int, variant: ItemVariant, required: string, free: string, incoming: string, shortage: string, made: bool}>  $rows
     */
    private function walk(ItemVariant $parent, string $qty, int $level, Collection $incoming, array &$used, array &$rows): void
    {
        foreach ($this->explosion->explode($parent, $qty, false) as $req) {
            $variant = $req['variant'];
            $id = $variant->id;

            $freeTotal = Decimal::of($this->stock->freeQuantity($id))->minus($used[$id] ?? BigDecimal::zero());
            $freeTotal = $freeTotal->isNegative() ? BigDecimal::zero() : $freeTotal;
            $takeFree = BigDecimal::min($freeTotal, Decimal::of($req['quantity']));
            $used[$id] = ($used[$id] ?? BigDecimal::zero())->plus($takeFree);

            $afterFree = Decimal::of($req['quantity'])->minus($takeFree);
            $in = Decimal::of($incoming[$id] ?? '0');
            $shortage = $afterFree->minus($in);
            $shortage = $shortage->isNegative() ? BigDecimal::zero() : $shortage;

            $rows[] = [
                'level' => $level,
                'variant' => $variant,
                'required' => $req['quantity'],
                'free' => (string) $takeFree->toScale(4),
                'incoming' => Decimal::round($incoming[$id] ?? '0', 4),
                'shortage' => (string) $shortage->toScale(4),
                'made' => $req['has_bom'],
            ];

            // A semi-finished component that is short is made from its own BOM.
            if ($req['has_bom'] && $shortage->isPositive()) {
                $this->walk($variant, (string) $shortage->toScale(4), $level + 1, $incoming, $used, $rows);
            }
        }
    }

    /**
     * Step 6: draft purchase orders for shortages and finished goods to buy,
     * one per vendor, each line linked to the order line it supplies.
     *
     * @param  list<array{item_variant_id: int, quantity: string, vendor_id: int, rate: string}>  $rows
     * @return list<PurchaseOrder>
     */
    public function createPurchaseOrders(SalesOrderLine $line, array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => Decimal::compare($r['quantity'], '0') > 0));
        if ($rows === []) {
            throw new DocumentException('Nothing to order.');
        }

        return DB::transaction(function () use ($line, $rows) {
            $created = [];

            foreach (collect($rows)->groupBy('vendor_id') as $vendorId => $vendorRows) {
                $lines = $vendorRows->map(function (array $r) {
                    $variant = ItemVariant::query()->with('item')->findOrFail($r['item_variant_id']);
                    if (! $variant->item->can_buy) {
                        throw new DocumentException("{$variant->item->code} cannot be bought.");
                    }

                    return [
                        'item_variant_id' => $variant->id, 'stage_id' => null, 'description' => null, 'quantity' => $r['quantity'],
                        'unit_id' => $variant->item->stock_unit_id, 'rate' => $r['rate'], 'expected_date' => null,
                    ];
                })->values()->all();

                $po = $this->orders->saveDraft(null, [
                    'vendor_id' => (int) $vendorId,
                    'order_date' => now()->format('Y-m-d'),
                    'notes' => "For {$line->order->number}",
                ], $lines);

                foreach ($po->lines as $poLine) {
                    SupplyAllocation::query()->create([
                        'sales_order_line_id' => $line->id,
                        'supply_type' => $poLine->getMorphClass(),
                        'supply_id' => $poLine->id,
                        'item_variant_id' => $poLine->item_variant_id,
                        'quantity' => Decimal::mul($poLine->quantity, $poLine->unit_factor, 4),
                        'created_by' => auth()->id(),
                    ]);
                }

                $created[] = $po->fresh(['lines']) ?? $po;
            }

            $this->salesOrders->refreshStatus($line->order);

            return $created;
        });
    }
}
