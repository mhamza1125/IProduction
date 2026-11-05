<?php

namespace App\Modules\Inventory\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The document behind a stock movement, recorded on every movement.
 */
final readonly class StockSource
{
    public function __construct(
        public Model $document,
        public CarbonInterface $date,
        public ?int $lineId = null,
        public ?int $salesOrderLineId = null,
        /** Employee or contractor who holds the stock (production issues). */
        public ?Model $holder = null,
        public ?string $notes = null,
    ) {}
}
