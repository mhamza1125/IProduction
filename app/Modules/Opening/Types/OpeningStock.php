<?php

namespace App\Modules\Opening\Types;

use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\OpeningContext;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/** Opening Stock: Dr Inventory, Cr Opening Balance (PKR). */
class OpeningStock extends BaseType
{
    public function key(): string
    {
        return 'stock';
    }

    public function label(): string
    {
        return 'Opening stock';
    }

    public function columns(): array
    {
        return ['sku' => 'Variant SKU', 'stage' => 'Stage name (empty = finished/raw)', 'status' => 'available, rejected or returned', 'location' => 'Location code (empty = Main Store)',
            'lot' => 'Lot number from the old system (optional)', 'quantity' => 'Quantity in stock units', 'unit_cost' => 'Unit cost in PKR', 'note' => 'Source note'];
    }

    public function example(): array
    {
        return ['sku' => 'GLV-RED-M', 'stage' => '', 'status' => 'available', 'location' => 'MAIN', 'lot' => 'OLD-1001', 'quantity' => '250', 'unit_cost' => '420.50', 'note' => 'Old system stock count'];
    }

    public function validate(array $row, OpeningContext $context): array
    {
        $e = [];
        if (! $this->variant($row['sku'])) {
            $e[] = "Unknown SKU \"{$row['sku']}\".";
        }
        if ($row['stage'] && ! $this->stage($row['stage'])) {
            $e[] = "Unknown stage \"{$row['stage']}\".";
        }
        if (! in_array($row['status'] ?: 'available', ['available', 'rejected', 'returned'], true)) {
            $e[] = 'Status must be available, rejected or returned.';
        }
        if ($row['location'] && ! Location::query()->where('code', $row['location'])->exists()) {
            $e[] = "Unknown location \"{$row['location']}\".";
        }
        if (! $this->positive($row['quantity'])) {
            $e[] = 'Quantity must be above zero.';
        }
        if (! $this->nonNegative($row['unit_cost'])) {
            $e[] = 'Unit cost must be zero or more.';
        }
        if ($row['lot'] && (Lot::query()->where('lot_number', $row['lot'])->exists() || ! $context->claim('lot:'.$row['lot']))) {
            $e[] = "Lot number \"{$row['lot']}\" is already used.";
        }

        return $e;
    }

    public function post(OpeningBatch $batch, Collection $rows): array
    {
        $stock = app(StockLedger::class);
        $value = '0';
        foreach ($rows as $row) {
            $d = $row->data;
            $variant = $this->variant($d['sku']);
            $lot = $stock->createLot($variant->id, 'opening', Decimal::round((string) $d['unit_cost'], 4), $batch->batch_date, $batch, ['notes' => $d['note'] ?: null]);
            if ($d['lot']) {
                $lot->update(['lot_number' => $d['lot']]);
            }
            $location = $d['location'] ? Location::query()->where('code', $d['location'])->firstOrFail() : Location::mainStore();
            $stock->receive(new StockBucket($variant->id, $this->stage($d['stage'])?->id, $d['status'] ?: StockStatus::AVAILABLE, $location->id, $lot->id),
                (string) $d['quantity'], new StockSource($batch, $batch->batch_date, lineId: $row->id, notes: $d['note'] ?: 'Opening stock'));
            $row->update(['result_type' => $lot->getMorphClass(), 'result_id' => $lot->id]);
            $value = Decimal::add($value, Decimal::mul((string) $d['quantity'], (string) $d['unit_cost']));
        }
        $this->journal($batch, [['account' => 'inventory', 'amount' => $value, 'side' => 'debit', 'currency' => 'PKR']]);
        $this->add('PKR', $value);

        return $this->takeTotals();
    }
}
