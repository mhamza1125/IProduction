<?php

namespace App\Modules\Opening\Types;

use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Models\RouteStage;
use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\OpeningContext;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Services\Ptcs;
use App\Modules\Production\Services\PtcTransactions;
use App\Modules\Sales\Models\SalesOrder;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/**
 * Running PTCs: a PTC starting at its current stage with the pieces waiting
 * there, optional material already issued to it, and an optional order link
 * (an order number in this system). Dr Inventory, Cr Opening Balance.
 */
class RunningPtcs extends BaseType
{
    public function key(): string
    {
        return 'running_ptcs';
    }

    public function label(): string
    {
        return 'Running PTCs';
    }

    public function columns(): array
    {
        return ['ptc_ref' => 'Old system job number', 'sku' => 'Variant SKU being made', 'current_stage' => 'Stage the pieces have reached', 'quantity' => 'Pieces at that stage',
            'unit_cost' => 'Cost so far per piece (PKR)', 'order_number' => 'Sales order number in this system (optional)',
            'material_sku' => 'Material already issued (optional)', 'material_qty' => 'Material quantity held', 'material_cost' => 'Material unit cost (PKR)'];
    }

    public function example(): array
    {
        return ['ptc_ref' => 'JOB-OLD-77', 'sku' => 'GLV-RED-M', 'current_stage' => 'Stitching', 'quantity' => '300', 'unit_cost' => '210', 'order_number' => '',
            'material_sku' => '', 'material_qty' => '', 'material_cost' => ''];
    }

    public function validate(array $row, OpeningContext $context): array
    {
        $e = [];
        $variant = $this->variant($row['sku']);
        $stage = $this->stage($row['current_stage']);
        if (! $variant) {
            $e[] = "Unknown SKU \"{$row['sku']}\".";
        } elseif (! $variant->item->can_make) {
            $e[] = "{$row['sku']} is not made.";
        } else {
            $route = ProductionRoute::query()->where('item_id', $variant->item_id)->where('is_default', true)->first();
            if (! $route) {
                $e[] = "{$row['sku']} has no default route.";
            } elseif (! $stage || ! RouteStage::query()->where('route_id', $route->id)->where('stage_id', $stage->id)->exists()) {
                $e[] = "Stage \"{$row['current_stage']}\" is not on the route of {$row['sku']}.";
            }
        }
        if (! $this->positive($row['quantity']) || ! $this->nonNegative($row['unit_cost'])) {
            $e[] = 'Quantity must be above zero and unit cost zero or more.';
        }
        if ($row['order_number']) {
            $order = SalesOrder::query()->with('lines')->where('number', $row['order_number'])->first();
            if (! $order || ! $variant || ! $order->lines->contains('item_variant_id', $variant->id)) {
                $e[] = "Order {$row['order_number']} has no line for {$row['sku']}.";
            }
        }
        if ($row['material_sku'] && (! $this->variant($row['material_sku']) || ! $this->positive($row['material_qty']) || ! $this->nonNegative($row['material_cost']))) {
            $e[] = 'Material needs a known SKU, a quantity above zero and a unit cost.';
        }
        if (! $row['ptc_ref'] || ! $context->claim('ptc:'.$row['ptc_ref'])) {
            $e[] = 'ptc_ref is required and must be unique in the file.';
        } elseif (Ptc::query()->where('notes', 'like', "Opening job {$row['ptc_ref']}%")->exists()) {
            $e[] = "Job {$row['ptc_ref']} was already imported.";
        }

        return $e;
    }

    public function post(OpeningBatch $batch, Collection $rows): array
    {
        $ptcs = app(Ptcs::class);
        $tx = app(PtcTransactions::class);
        $value = '0';
        foreach ($rows as $row) {
            $d = $row->data;
            $variant = $this->variant($d['sku']);
            $stage = $this->stage($d['current_stage']);
            $route = ProductionRoute::query()->where('item_id', $variant->item_id)->where('is_default', true)->firstOrFail();
            $line = $d['order_number'] ? SalesOrder::query()->where('number', $d['order_number'])->firstOrFail()->lines()->where('item_variant_id', $variant->id)->first() : null;
            $ptc = $ptcs->approve($ptcs->save(null, ['item_variant_id' => $variant->id, 'planned_qty' => (string) $d['quantity'], 'route_id' => $route->id, 'start_stage_id' => $stage->id,
                'sales_order_line_id' => $line?->id, 'start_date' => $batch->batch_date->toDateString(), 'notes' => "Opening job {$d['ptc_ref']} ({$batch->number})"]));
            $tx->openingPieces($ptc, $ptc->stages()->where('stage_id', $stage->id)->firstOrFail(), (string) $d['quantity'], (string) $d['unit_cost'], $batch->batch_date, $batch);
            $value = Decimal::add($value, Decimal::mul((string) $d['quantity'], (string) $d['unit_cost']));
            if ($d['material_sku']) {
                $tx->openingMaterial($ptc->fresh(), $this->variant($d['material_sku'])->id, (string) $d['material_qty'], (string) $d['material_cost'], $batch->batch_date, $batch);
                $value = Decimal::add($value, Decimal::mul((string) $d['material_qty'], (string) $d['material_cost']));
            }
            $row->update(['result_type' => $ptc->getMorphClass(), 'result_id' => $ptc->id]);
        }
        $this->journal($batch, [['account' => 'inventory', 'amount' => $value, 'side' => 'debit', 'currency' => 'PKR']]);
        $this->add('PKR', $value);

        return $this->takeTotals();
    }
}
