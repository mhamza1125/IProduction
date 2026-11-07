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
use App\Modules\People\Models\Contractor;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/** Company stock held by contractors (In production at the contractor's location). Dr Inventory, Cr Opening Balance. */
class ContractorStock extends BaseType
{
    public function key(): string
    {
        return 'contractor_stock';
    }

    public function label(): string
    {
        return 'Contractor stock';
    }

    public function columns(): array
    {
        return ['contractor_code' => 'Contractor code', 'sku' => 'Variant SKU', 'stage' => 'Stage (optional)', 'quantity' => 'Quantity held', 'lot' => 'Old lot number (optional)', 'unit_cost' => 'Unit cost (PKR)'];
    }

    public function example(): array
    {
        return ['contractor_code' => 'CON-0001', 'sku' => 'PANEL-WHT', 'stage' => '', 'quantity' => '3200', 'lot' => '', 'unit_cost' => '2.10'];
    }

    public function validate(array $row, OpeningContext $context): array
    {
        $e = [];
        if (! $this->party(Contractor::class, $row['contractor_code'])) {
            $e[] = "Unknown contractor \"{$row['contractor_code']}\".";
        }
        if (! $this->variant($row['sku'])) {
            $e[] = "Unknown SKU \"{$row['sku']}\".";
        }
        if ($row['stage'] && ! $this->stage($row['stage'])) {
            $e[] = "Unknown stage \"{$row['stage']}\".";
        }
        if (! $this->positive($row['quantity']) || ! $this->nonNegative($row['unit_cost'])) {
            $e[] = 'Quantity must be above zero and unit cost zero or more.';
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
            $contractor = $this->party(Contractor::class, $d['contractor_code']);
            $variant = $this->variant($d['sku']);
            $lot = $stock->createLot($variant->id, 'opening', Decimal::round((string) $d['unit_cost'], 4), $batch->batch_date, $batch, ['contractor_id' => $contractor->getKey(), 'notes' => 'Held by contractor at go-live']);
            if ($d['lot']) {
                $lot->update(['lot_number' => $d['lot']]);
            }
            $stock->receive(new StockBucket($variant->id, $this->stage($d['stage'])?->id, StockStatus::IN_PRODUCTION, Location::forContractor($contractor)->id, $lot->id),
                (string) $d['quantity'], new StockSource($batch, $batch->batch_date, lineId: $row->id, holder: $contractor, notes: 'Opening contractor stock'));
            $row->update(['result_type' => $lot->getMorphClass(), 'result_id' => $lot->id]);
            $value = Decimal::add($value, Decimal::mul((string) $d['quantity'], (string) $d['unit_cost']));
        }
        $this->journal($batch, [['account' => 'inventory', 'amount' => $value, 'side' => 'debit', 'currency' => 'PKR']]);
        $this->add('PKR', $value);

        return $this->takeTotals();
    }
}
