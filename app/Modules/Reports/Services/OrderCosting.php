<?php

namespace App\Modules\Reports\Services;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Services\BomExplosion;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryAllocation;
use App\Modules\Delivery\Models\DeliveryReturn;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Models\InvoiceLine;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Production\Models\ContractorJob;
use App\Modules\Production\Models\ContractorJobTransaction;
use App\Modules\Production\Models\MaterialProcess;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcCost;
use App\Modules\Production\Models\RejectionDecision;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Models\SupplyAllocation;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Support\Collection;

/**
 * Order cost (specification, Module 12). Direct cost only, in PKR: overhead
 * (non-production salaries, rent, utilities) is not allocated.
 *
 * Actual direct cost = goods delivered at lot cost − returns at the same cost
 *                      + cost still in production for the order (PTCs not yet released).
 * "Where it came from" breaks the made/bought supply down by category.
 * Planned cost = BOM materials at standard purchase cost + route wage/contractor rates.
 */
class OrderCosting
{
    public function __construct(private readonly BomExplosion $explosion) {}

    /**
     * @return array{lines: Collection<int, array<string, mixed>>, categories: array<string, string>, actual: string, planned: string, revenue: string, currency: string, delivered_qty: string, sources: Collection<int, array{label: string, number: string, url: string}>}
     */
    public function forOrder(SalesOrder $order): array
    {
        $order->loadMissing(['lines.variant.item', 'customer']);
        $lines = $order->lines->map(fn (SalesOrderLine $l) => $this->forLine($l));

        $categories = [];
        foreach ($lines as $l) {
            foreach ($l['categories'] as $k => $v) {
                $categories[$k] = Decimal::add($categories[$k] ?? '0', $v);
            }
        }

        return [
            'lines' => $lines,
            'categories' => array_filter($categories, fn ($v) => ! Decimal::isZero($v)),
            'actual' => Decimal::sum($lines->pluck('actual')),
            'planned' => Decimal::sum($lines->pluck('planned')),
            'revenue' => Decimal::sum($lines->pluck('revenue')),
            'currency' => $order->currency_code,
            'delivered_qty' => Decimal::sum($lines->pluck('net_delivered'), 4),
            'sources' => $this->sources($order),
        ];
    }

    /** @return array<string, mixed> */
    public function forLine(SalesOrderLine $line): array
    {
        $delivered = DeliveryAllocation::query()->whereHas('line', fn ($q) => $q->where('sales_order_line_id', $line->id)->whereHas('delivery', fn ($d) => $d->where('status', 'dispatched')))->get();
        $deliveredCost = Decimal::sum($delivered->map(fn ($a) => Decimal::mul($a->quantity, $a->unit_cost ?? '0')));
        $deliveredQty = Decimal::sum($delivered->pluck('quantity'), 4);

        $returns = DeliveryReturn::query()->whereNotNull('posted_at')->whereHas('deliveryLine', fn ($q) => $q->where('sales_order_line_id', $line->id))->get();
        $returnCost = Decimal::sum($returns->map(fn ($r) => Decimal::mul($r->quantity, $r->unit_cost ?? '0')));
        $returnQty = Decimal::sum($returns->pluck('quantity'), 4);

        $ptcIds = Ptc::query()->where('sales_order_line_id', $line->id)->pluck('id');
        $costs = PtcCost::query()->whereIn('ptc_id', $ptcIds)->get();
        $wip = Decimal::sum($costs->pluck('amount'));
        $wip = Decimal::isNegative($wip) ? '0.00' : $wip;

        // Where the cost of made / bought supply came from.
        $cat = [];
        $poType = (new PurchaseOrderLine)->getMorphClass();
        foreach (SupplyAllocation::query()->where('sales_order_line_id', $line->id)->where('supply_type', $poType)->with('supply')->get() as $a) {
            $po = $a->supply;
            if ($po instanceof PurchaseOrderLine && Decimal::compare($a->fulfilled_quantity, '0') > 0) {
                $perStockUnit = Decimal::div($po->rate, $po->unit_factor ?: '1', 4);
                $cat['Purchased goods and materials'] = Decimal::add($cat['Purchased goods and materials'] ?? '0', Decimal::mul($a->fulfilled_quantity, $perStockUnit));
            }
        }
        $labels = ['material' => 'Materials issued to production', 'stock_in' => 'Part-made stock entered production', 'extra_material' => 'Extra material issued',
            'wage' => 'Wage worker cost', 'contractor' => 'Contractor cost (in PTCs)', 'rework' => 'Rework cost'];
        foreach ($costs->whereIn('category', array_keys($labels))->groupBy('category') as $k => $rows) {
            $cat[$labels[$k]] = Decimal::add($cat[$labels[$k]] ?? '0', Decimal::sum($rows->pluck('amount')));
        }
        $jobIds = ContractorJob::query()->where('sales_order_line_id', $line->id)->pluck('id');
        $jobTx = ContractorJobTransaction::query()->whereIn('contractor_job_id', $jobIds)->whereIn('type', ['receipt', 'consume'])->get();
        if ($jobTx->isNotEmpty()) {
            $cat['Contractor jobs (labour)'] = Decimal::sum($jobTx->where('type', 'receipt')->pluck('amount'));
            $cat['Contractor jobs (material used)'] = Decimal::sum($jobTx->where('type', 'consume')->pluck('amount'));
        }
        $processes = MaterialProcess::query()->where('sales_order_line_id', $line->id)->whereNotNull('posted_at')->get();
        if ($processes->isNotEmpty()) {
            $cat['Material processing'] = Decimal::sum($processes->map(fn ($p) => Decimal::sub(Decimal::add($p->input_cost ?? '0', $p->labor_cost ?? '0'), $p->recoverable_value ?? '0')));
        }
        $scrap = RejectionDecision::query()->where('decision', 'scrap')->whereNotNull('posted_at')
            ->whereHas('rejection', fn ($q) => $q->whereIn('ptc_id', $ptcIds))->with('rejection.lot')->get();
        if ($scrap->isNotEmpty()) {
            $cat['Rejection and scrap loss'] = Decimal::sum($scrap->map(fn ($d) => Decimal::mul((string) $d->quantity, $d->rejection->lot->unit_cost ?? '0')));
        }

        $actual = Decimal::add(Decimal::sub($deliveredCost, $returnCost), $wip);
        $netDelivered = Decimal::sub($deliveredQty, $returnQty, 4);
        $planned = $this->plannedCost($line->variant, $line->quantity);
        $revenue = Decimal::sum(InvoiceLine::query()->where('sales_order_line_id', $line->id)->whereHas('invoice', fn ($q) => $q->whereNotNull('posted_at'))->pluck('amount'));
        $reservedCost = Decimal::sum(Reservation::query()->where('demand_type', $line->getMorphClass())->where('demand_id', $line->id)->where('status', Reservation::ACTIVE)
            ->with('lot')->get()->map(fn ($r) => Decimal::mul($r->open(), $r->lot->unit_cost ?? '0')));

        return [
            'line' => $line,
            'ordered' => $line->quantity,
            'net_delivered' => $netDelivered,
            'delivered_cost' => $deliveredCost,
            'return_cost' => $returnCost,
            'wip' => $wip,
            'reserved_cost' => $reservedCost,
            'actual' => $actual,
            'planned' => $planned,
            'planned_unit' => Decimal::compare($line->quantity, '0') > 0 ? Decimal::div($planned, $line->quantity, 4) : null,
            'actual_unit' => Decimal::compare($netDelivered, '0') > 0 ? Decimal::div(Decimal::sub($deliveredCost, $returnCost), $netDelivered, 4) : null,
            'revenue' => $revenue,
            'categories' => $cat,
        ];
    }

    /** Standard cost: BOM materials at purchase cost + route labour rates, or purchase cost when bought. */
    public function plannedCost(ItemVariant $variant, string $quantity): string
    {
        $variant->loadMissing('item');
        if (! $variant->item->can_make || $variant->item->activeBom() === null) {
            return Decimal::mul($quantity, $variant->purchaseCost() ?? '0');
        }
        try {
            $rows = $this->explosion->explode($variant, $quantity, true);
        } catch (DocumentException) {
            return '0.00';
        }
        $materials = Decimal::sum(collect($rows)->filter(fn ($r) => ! $r['has_bom'])->map(fn ($r) => Decimal::mul($r['quantity'], $r['variant']->purchaseCost() ?? '0')));
        $route = ProductionRoute::query()->with('stages')->where('item_id', $variant->item_id)->where('is_default', true)->first();
        $labour = $route ? Decimal::sum($route->stages->map(fn ($s) => Decimal::mul($quantity, ($s->default_work_type === 'contractor' ? $s->contractor_rate : $s->wage_rate) ?? '0'))) : '0';

        return Decimal::add($materials, $labour);
    }

    /** @return Collection<int, array{label: string, number: string, url: string}> */
    private function sources(SalesOrder $order): Collection
    {
        $lineIds = $order->lines->pluck('id');
        $out = collect();
        foreach (Delivery::query()->whereHas('lines', fn ($q) => $q->whereIn('sales_order_line_id', $lineIds))->where('status', '!=', 'cancelled')->get() as $d) {
            $out->push(['label' => 'Delivery', 'number' => $d->number, 'url' => route('delivery.deliveries.show', $d)]);
        }
        foreach (Invoice::query()->whereHas('lines', fn ($q) => $q->whereIn('sales_order_line_id', $lineIds))->where('status', '!=', 'cancelled')->get() as $i) {
            $out->push(['label' => 'Invoice', 'number' => $i->number, 'url' => route('delivery.invoices.show', $i)]);
        }
        foreach (Ptc::query()->whereIn('sales_order_line_id', $lineIds)->get() as $p) {
            $out->push(['label' => 'PTC', 'number' => $p->number, 'url' => route('production.ptcs.show', $p)]);
        }
        foreach (SupplyAllocation::query()->whereIn('sales_order_line_id', $lineIds)->where('supply_type', (new PurchaseOrderLine)->getMorphClass())->with('supply.order')->get() as $a) {
            if ($a->supply instanceof PurchaseOrderLine) {
                $out->push(['label' => 'Purchase order', 'number' => $a->supply->order->number, 'url' => route('purchasing.orders.show', $a->supply->purchase_order_id)]);
            }
        }
        foreach (ContractorJob::query()->whereIn('sales_order_line_id', $lineIds)->get() as $j) {
            $out->push(['label' => 'Contractor job', 'number' => $j->number, 'url' => route('production.jobs.show', $j)]);
        }
        foreach (MaterialProcess::query()->whereIn('sales_order_line_id', $lineIds)->get() as $m) {
            $out->push(['label' => 'Material processing', 'number' => $m->number, 'url' => route('production.processes.show', $m)]);
        }
        foreach (DeliveryReturn::query()->whereHas('deliveryLine', fn ($q) => $q->whereIn('sales_order_line_id', $lineIds))->get() as $r) {
            $out->push(['label' => 'Return', 'number' => $r->number, 'url' => route('delivery.returns.show', $r)]);
        }

        return $out->unique('number')->values();
    }
}
