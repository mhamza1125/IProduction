<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockAdjustmentLine;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Closure;

/**
 * Posts a stock adjustment: increases create stock (a new Adjustment lot
 * unless an existing lot is chosen), decreases take stock out (the chosen
 * lot, else FIFO). A shortage on any line rolls back the whole document.
 *
 * Accounting entries (Dr/Cr Inventory against Stock Adjustment) are added by
 * the Finance module through the $journal hook.
 */
class PostStockAdjustment
{
    /** @var list<Closure(StockAdjustment, list<array{line: StockAdjustmentLine, value: string}>): void> */
    private static array $afterStock = [];

    public function __construct(
        private readonly DocumentWorkflow $workflow,
        private readonly StockLedger $stock,
    ) {}

    /**
     * Lets another module add effects in the same transaction (e.g. journals).
     *
     * @param  Closure(StockAdjustment, list<array{line: StockAdjustmentLine, value: string}>): void  $hook
     */
    public static function afterStock(Closure $hook): void
    {
        self::$afterStock[] = $hook;
    }

    public function __invoke(StockAdjustment $adjustment, ?string $overrideReason = null): StockAdjustment
    {
        return $this->workflow->post($adjustment, function (StockAdjustment $doc) {
            $lines = $doc->lines()->get();

            if ($lines->isEmpty()) {
                throw new DocumentException('Add at least one line before posting.');
            }

            $values = [];

            foreach ($lines as $line) {
                $values[] = ['line' => $line, 'value' => $this->postLine($doc, $line)];
            }

            foreach (self::$afterStock as $hook) {
                $hook($doc, $values);
            }
        }, $overrideReason);
    }

    /**
     * @return string signed stock value change (PKR)
     */
    private function postLine(StockAdjustment $doc, StockAdjustmentLine $line): string
    {
        $source = new StockSource($doc, $doc->adjustment_date, $line->id, notes: $line->notes);
        $quantity = Decimal::of($line->quantity);

        if ($quantity->isZero()) {
            return '0';
        }

        if ($quantity->isPositive()) {
            $lotId = $line->lot_id ?? $this->stock->createLot(
                $line->item_variant_id, 'adjustment', $line->unit_cost, $doc->adjustment_date, $doc, ['notes' => $doc->number],
            )->id;

            $movement = $this->stock->receive(
                new StockBucket($line->item_variant_id, $line->stage_id, $line->status, $line->location_id, $lotId),
                (string) $quantity,
                $source,
                $line->lot_id ? null : $line->unit_cost,
            );

            return (string) ($movement->total_cost ?? '0');
        }

        $issued = $this->stock->issue(
            $line->item_variant_id,
            (string) $quantity->abs(),
            $source,
            stageId: $line->stage_id,
            status: $line->status,
            locationId: $line->location_id,
            lotId: $line->lot_id,
        );

        return Decimal::sub('0', Decimal::sum(array_map(fn ($i) => $i['movement']->total_cost ?? '0', $issued)));
    }
}
