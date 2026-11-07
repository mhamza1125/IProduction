<?php

namespace App\Modules\Opening\Types;

use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\OpeningContext;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\PurchaseOrders;
use App\Support\Decimal;
use App\Support\Documents\DocumentWorkflow;
use Illuminate\Support\Collection;

/** Open purchase orders with remaining quantities and rates (PKR); rows with the same po_ref form one PO. */
class OpenPurchaseOrders extends BaseType
{
    /** @var array<string, int> */
    private array $refVendor = [];

    public function key(): string
    {
        return 'open_pos';
    }

    public function label(): string
    {
        return 'Open purchase orders';
    }

    public function columns(): array
    {
        return ['po_ref' => 'Old system PO number (rows with the same ref form one PO)', 'vendor_code' => 'Vendor code', 'sku' => 'Variant SKU',
            'remaining_qty' => 'Quantity still to receive (stock unit)', 'rate' => 'Rate in PKR per stock unit', 'expected_date' => 'Expected date (optional)'];
    }

    public function example(): array
    {
        return ['po_ref' => 'PO-OLD-310', 'vendor_code' => 'VEN-0001', 'sku' => 'LEATHER-BLK', 'remaining_qty' => '120', 'rate' => '540', 'expected_date' => '2026-12-01'];
    }

    public function validate(array $row, OpeningContext $context): array
    {
        $e = [];
        $vendor = $this->party(Vendor::class, $row['vendor_code']);
        if (! $vendor) {
            $e[] = "Unknown vendor \"{$row['vendor_code']}\".";
        }
        $variant = $this->variant($row['sku']);
        if (! $variant) {
            $e[] = "Unknown SKU \"{$row['sku']}\".";
        } elseif (! $variant->item->can_buy) {
            $e[] = "{$row['sku']} is not bought.";
        }
        if (! $this->positive($row['remaining_qty']) || ! $this->nonNegative($row['rate'])) {
            $e[] = 'Remaining quantity must be above zero and rate zero or more.';
        }
        $ref = (string) $row['po_ref'];
        if ($ref === '') {
            $e[] = 'po_ref is required.';
        } elseif ($vendor) {
            if (isset($this->refVendor[$ref]) && $this->refVendor[$ref] !== $vendor->id) {
                $e[] = "PO {$ref} has rows for two vendors.";
            }
            $this->refVendor[$ref] = $vendor->id;
            if (PurchaseOrder::query()->where('notes', 'like', "Opening PO {$ref}%")->exists()) {
                $e[] = "PO {$ref} was already imported.";
            }
        }

        return $e;
    }

    public function post(OpeningBatch $batch, Collection $rows): array
    {
        foreach ($rows->groupBy(fn ($r) => $r->data['po_ref']) as $ref => $group) {
            $vendor = $this->party(Vendor::class, $group->first()->data['vendor_code']);
            $po = app(PurchaseOrders::class)->saveDraft(null, ['vendor_id' => $vendor->getKey(), 'order_date' => $batch->batch_date->toDateString(), 'notes' => "Opening PO {$ref} ({$batch->number})"],
                $group->map(function ($r) {
                    $variant = $this->variant($r->data['sku']);

                    return ['item_variant_id' => $variant->id, 'stage_id' => null, 'description' => null, 'quantity' => (string) $r->data['remaining_qty'],
                        'unit_id' => $variant->item->stock_unit_id, 'rate' => (string) $r->data['rate'], 'expected_date' => $r->data['expected_date'] ?: null];
                })->values()->all());
            app(DocumentWorkflow::class)->approve($po);
            foreach ($group as $r) {
                $r->update(['result_type' => $po->getMorphClass(), 'result_id' => $po->id]);
                $this->add('PKR', Decimal::mul((string) $r->data['remaining_qty'], (string) $r->data['rate']));
            }
        }

        return $this->takeTotals();
    }
}
