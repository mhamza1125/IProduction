<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockBalance;

/**
 * Where a quantity sits: variant, stage (NULL = not in a production stage),
 * status, location and lot.
 */
final readonly class StockBucket
{
    public function __construct(
        public int $variantId,
        public ?int $stageId,
        public string $status,
        public int $locationId,
        public int $lotId,
    ) {}

    public function key(): string
    {
        return StockBalance::bucketKey($this->variantId, $this->stageId, $this->status, $this->locationId, $this->lotId);
    }

    public function with(?string $status = null, ?int $locationId = null, ?int $stageId = null, bool $clearStage = false): self
    {
        return new self(
            $this->variantId,
            $clearStage ? null : ($stageId ?? $this->stageId),
            $status ?? $this->status,
            $locationId ?? $this->locationId,
            $this->lotId,
        );
    }
}
