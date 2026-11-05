<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockStatus;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only writer of stock. Every change is an append-only movement plus the
 * matching balance update, inside the caller's transaction.
 *
 * Rules enforced here (specification, Module 4):
 *  - stock never goes negative: a shortage throws, rolling back the whole document;
 *  - issues use the demand's reserved lots first, then free stock FIFO, and never
 *    another demand's reservation (unless the user picked a specific lot that is free);
 *  - free stock = Available − active reservations;
 *  - rows are locked per variant while checking and changing stock, so two users
 *    cannot reserve or issue the same stock at the same time.
 */
class StockLedger
{
    public function __construct(private readonly DocumentNumber $numbers) {}

    /**
     * Creates a lot for an inbound source (goods receipt, production output, opening stock...).
     *
     * @param  array<string, mixed>  $extra  vendor_id, contractor_id, notes
     */
    public function createLot(int $variantId, string $origin, ?string $unitCost, CarbonInterface $date, ?Model $source = null, array $extra = []): Lot
    {
        $this->assertTransaction();

        if (! array_key_exists($origin, Lot::ORIGINS)) {
            throw new LogicException("Unknown lot origin [{$origin}].");
        }

        return Lot::query()->create([
            'lot_number' => $this->numbers->next('lot', $date),
            'item_variant_id' => $variantId,
            'origin' => $origin,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'received_date' => $date->format('Y-m-d'),
            'unit_cost' => $unitCost,
        ] + $extra);
    }

    /**
     * Puts quantity into a bucket (variant, stage, status, location, lot).
     */
    public function receive(StockBucket $to, string $quantity, StockSource $source, ?string $unitCost = null): StockMovement
    {
        $this->assertTransaction();
        $this->assertPositive($quantity);

        $lot = Lot::query()->findOrFail($to->lotId);
        $unitCost ??= $lot->unit_cost;

        $this->changeBalance($to, $quantity);

        return $this->writeMovement($to, $quantity, '0', $unitCost, $source);
    }

    /**
     * Takes quantity out of one exact bucket (a known lot). Throws on shortage.
     */
    public function remove(StockBucket $from, string $quantity, StockSource $source): StockMovement
    {
        $this->assertTransaction();
        $this->assertPositive($quantity);
        $this->lockVariant($from->variantId);

        $lot = Lot::query()->findOrFail($from->lotId);
        $this->changeBalance($from, '-'.$quantity);

        return $this->writeMovement($from, '0', $quantity, $lot->unit_cost, $source);
    }

    /**
     * Moves quantity between buckets of the same lot (status, stage or location
     * change), keeping the lot and its cost. Returns [out, in] movements.
     *
     * @return array{0: StockMovement, 1: StockMovement}
     */
    public function transfer(StockBucket $from, StockBucket $to, string $quantity, StockSource $source): array
    {
        if ($from->lotId !== $to->lotId || $from->variantId !== $to->variantId) {
            throw new LogicException('A transfer keeps the same variant and lot.');
        }

        $out = $this->remove($from, $quantity, $source);
        $in = $this->receive($to, $quantity, $source, $out->unit_cost);

        return [$out, $in];
    }

    /**
     * Chooses lots for a stock-out from Available (or another status) stock:
     * a lot the user picked, else the demand's reserved lots, then free stock FIFO.
     * Does not write anything; pass the result to remove()/transfer().
     *
     * @return list<array{bucket: StockBucket, quantity: string}>
     */
    public function allocate(
        int $variantId,
        string $quantity,
        ?int $stageId = null,
        string $status = StockStatus::AVAILABLE,
        ?int $locationId = null,
        ?Model $demand = null,
        ?int $lotId = null,
    ): array {
        $this->assertTransaction();
        $this->assertPositive($quantity);
        $this->lockVariant($variantId);

        $balances = StockBalance::query()
            ->join('lots', 'lots.id', '=', 'stock_balances.lot_id')
            ->where('stock_balances.item_variant_id', $variantId)
            ->where('stock_balances.status', $status)
            ->where('stock_balances.quantity', '>', 0)
            ->when($stageId === null, fn ($q) => $q->whereNull('stock_balances.stage_id'), fn ($q) => $q->where('stock_balances.stage_id', $stageId))
            ->when($locationId !== null, fn ($q) => $q->where('stock_balances.location_id', $locationId))
            ->when($lotId !== null, fn ($q) => $q->where('stock_balances.lot_id', $lotId))
            ->orderBy('lots.received_date')->orderBy('lots.id')->orderBy('stock_balances.location_id')
            ->get(['stock_balances.*']);

        // Reservations only hold Available stock.
        $others = $status === StockStatus::AVAILABLE ? $this->openReservations($variantId, $stageId, exceptDemand: $demand) : collect();
        $ownLotIds = $demand !== null && $status === StockStatus::AVAILABLE
            ? $this->openReservations($variantId, $stageId, onlyDemand: $demand)->whereNotNull('lot_id')->pluck('lot_id')->unique()->all()
            : [];

        // Total this demand may take = everything here − what others hold.
        $othersTotal = Decimal::sum($others->map(fn (Reservation $r) => $r->open()), Decimal::QUANTITY);
        $remainingForUs = Decimal::of(Decimal::sum($balances->pluck('quantity'), Decimal::QUANTITY))->minus($othersTotal);

        // Own reserved lots first, then FIFO.
        $ordered = $balances->sortBy(fn (StockBalance $b) => in_array($b->lot_id, $ownLotIds, true) ? 0 : 1, SORT_REGULAR, false)->values();

        $needed = Decimal::of($quantity);
        $allocations = [];

        foreach ($ordered as $balance) {
            if ($needed->isZero() || ! $remainingForUs->isPositive()) {
                break;
            }

            // A lot reserved for another demand keeps that part.
            $lotHeldByOthers = Decimal::of(Decimal::sum(
                $others->where('lot_id', $balance->lot_id)->map(fn (Reservation $r) => $r->open()),
                Decimal::QUANTITY,
            ));
            $usable = Decimal::of($balance->quantity)->minus($lotHeldByOthers);
            $take = BigDecimal::min($usable, $needed, $remainingForUs);

            if ($take->isPositive()) {
                $allocations[] = [
                    'bucket' => new StockBucket($variantId, $balance->stage_id, $balance->status, $balance->location_id, $balance->lot_id),
                    'quantity' => (string) $take->toScale(Decimal::QUANTITY),
                ];
                $needed = $needed->minus($take);
                $remainingForUs = $remainingForUs->minus($take);
            }
        }

        if ($needed->isPositive()) {
            $shortBy = (string) $needed->toScale(Decimal::QUANTITY);
            throw new DocumentException('Not enough '.StockStatus::LABELS[$status].' stock for '.$this->variantLabel($variantId).": short by {$shortBy}.");
        }

        return $allocations;
    }

    /**
     * Allocates (see allocate()) and writes the out-movements. When the stock
     * is for a demand, its reservations are consumed by the issued quantity.
     *
     * @return list<array{bucket: StockBucket, quantity: string, movement: StockMovement}>
     */
    public function issue(
        int $variantId,
        string $quantity,
        StockSource $source,
        ?int $stageId = null,
        string $status = StockStatus::AVAILABLE,
        ?int $locationId = null,
        ?Model $demand = null,
        ?int $lotId = null,
    ): array {
        $issued = [];

        foreach ($this->allocate($variantId, $quantity, $stageId, $status, $locationId, $demand, $lotId) as $allocation) {
            $issued[] = $allocation + ['movement' => $this->remove($allocation['bucket'], $allocation['quantity'], $source)];
        }

        if ($demand !== null && $status === StockStatus::AVAILABLE) {
            $this->consumeReservations($demand, $variantId, $stageId, $issued);
        }

        return $issued;
    }

    /**
     * Holds free stock for a demand (sales order line). Lot-level when $lotId is given.
     */
    public function reserve(Model $demand, int $variantId, string $quantity, ?int $stageId = null, ?int $lotId = null): Reservation
    {
        $this->assertTransaction();
        $this->assertPositive($quantity);
        $this->lockVariant($variantId);

        $free = $this->freeQuantity($variantId, $stageId, $lotId);

        if (Decimal::compare($quantity, $free) > 0) {
            throw new DocumentException("Only {$free} free for ".$this->variantLabel($variantId).", cannot reserve {$quantity}.");
        }

        return Reservation::query()->create([
            'demand_type' => $demand->getMorphClass(),
            'demand_id' => $demand->getKey(),
            'item_variant_id' => $variantId,
            'stage_id' => $stageId,
            'lot_id' => $lotId,
            'quantity' => Decimal::round($quantity, Decimal::QUANTITY),
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Releases what is still open on a reservation (order cancelled or closed,
     * or a user freeing the stock). A reason is required.
     */
    public function release(Reservation $reservation, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required to release a reservation.');
        }

        DB::transaction(function () use ($reservation, $reason) {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($locked->status !== Reservation::ACTIVE) {
                throw new DocumentException('This reservation is no longer active.');
            }

            $locked->update([
                'status' => Reservation::RELEASED,
                'release_reason' => $reason,
                'released_by' => auth()->id(),
                'released_at' => now(),
            ]);
        });

        $reservation->refresh();
    }

    /**
     * Moves the open quantity of a reservation to another demand, with a reason.
     */
    public function reallocate(Reservation $reservation, Model $newDemand, string $reason): Reservation
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required to reallocate a reservation.');
        }

        return DB::transaction(function () use ($reservation, $newDemand, $reason) {
            $this->lockVariant($reservation->item_variant_id);
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($locked->status !== Reservation::ACTIVE) {
                throw new DocumentException('This reservation is no longer active.');
            }

            $open = $locked->open();
            $locked->update([
                'status' => Reservation::RELEASED,
                'release_reason' => 'Reallocated: '.$reason,
                'released_by' => auth()->id(),
                'released_at' => now(),
            ]);

            return Reservation::query()->create([
                'demand_type' => $newDemand->getMorphClass(),
                'demand_id' => $newDemand->getKey(),
                'item_variant_id' => $locked->item_variant_id,
                'stage_id' => $locked->stage_id,
                'lot_id' => $locked->lot_id,
                'quantity' => $open,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * Available quantity (optionally one stage / location / lot).
     */
    public function onHand(int $variantId, ?int $stageId = null, string $status = StockStatus::AVAILABLE, ?int $locationId = null, ?int $lotId = null): string
    {
        return Decimal::round((string) (StockBalance::query()
            ->where('item_variant_id', $variantId)
            ->where('status', $status)
            ->when($stageId === null, fn ($q) => $q->whereNull('stage_id'), fn ($q) => $q->where('stage_id', $stageId))
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->when($lotId !== null, fn ($q) => $q->where('lot_id', $lotId))
            ->sum('quantity') ?: '0'), Decimal::QUANTITY);
    }

    /**
     * Open reserved quantity for a variant (and stage / lot).
     */
    public function reservedQuantity(int $variantId, ?int $stageId = null, ?int $lotId = null): string
    {
        $open = $this->openReservations($variantId, $stageId)
            ->when($lotId !== null, fn ($c) => $c->where('lot_id', $lotId))
            ->map(fn (Reservation $r) => $r->open());

        return Decimal::sum($open, Decimal::QUANTITY);
    }

    /**
     * Free stock = Available − active reservations.
     */
    public function freeQuantity(int $variantId, ?int $stageId = null, ?int $lotId = null): string
    {
        if ($lotId !== null) {
            // A lot's free quantity also cannot exceed the variant's overall free quantity.
            $lotFree = Decimal::sub($this->onHand($variantId, $stageId, lotId: $lotId), $this->reservedQuantity($variantId, $stageId, $lotId), Decimal::QUANTITY);
            $variantFree = $this->freeQuantity($variantId, $stageId);

            return Decimal::compare($lotFree, $variantFree) < 0 ? $lotFree : $variantFree;
        }

        return Decimal::sub($this->onHand($variantId, $stageId), $this->reservedQuantity($variantId, $stageId), Decimal::QUANTITY);
    }

    /**
     * Locks every balance row of a variant until the transaction ends.
     */
    public function lockVariant(int $variantId): void
    {
        $this->assertTransaction();

        StockBalance::query()->where('item_variant_id', $variantId)->lockForUpdate()->get(['id']);
        Reservation::query()->where('item_variant_id', $variantId)->where('status', Reservation::ACTIVE)->lockForUpdate()->get(['id']);
    }

    /**
     * Recomputes stock_balances from stock_movements.
     *
     * @return int Number of buckets written
     */
    public function rebuildBalances(): int
    {
        return DB::transaction(function () {
            StockBalance::query()->delete();

            $rows = StockMovement::query()
                ->selectRaw('item_variant_id, stage_id, status, location_id, lot_id, SUM(qty_in) - SUM(qty_out) AS quantity')
                ->groupBy('item_variant_id', 'stage_id', 'status', 'location_id', 'lot_id')
                ->get();

            foreach ($rows as $row) {
                StockBalance::query()->create([
                    'bucket' => StockBalance::bucketKey($row->item_variant_id, $row->stage_id, $row->status, $row->location_id, $row->lot_id),
                    'item_variant_id' => $row->item_variant_id,
                    'stage_id' => $row->stage_id,
                    'status' => $row->status,
                    'location_id' => $row->location_id,
                    'lot_id' => $row->lot_id,
                    'quantity' => Decimal::round((string) $row->getAttribute('quantity'), Decimal::QUANTITY),
                ]);
            }

            return $rows->count();
        });
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function openReservations(int $variantId, ?int $stageId, ?Model $exceptDemand = null, ?Model $onlyDemand = null): Collection
    {
        return Reservation::query()
            ->where('item_variant_id', $variantId)
            ->where('status', Reservation::ACTIVE)
            ->when($stageId === null, fn ($q) => $q->whereNull('stage_id'), fn ($q) => $q->where('stage_id', $stageId))
            ->when($exceptDemand, fn ($q) => $q->whereNot(fn ($q) => $q->where('demand_type', $exceptDemand->getMorphClass())->where('demand_id', $exceptDemand->getKey())))
            ->when($onlyDemand, fn ($q) => $q->where('demand_type', $onlyDemand->getMorphClass())->where('demand_id', $onlyDemand->getKey()))
            ->get();
    }

    /**
     * Reduces the demand's reservations by what was issued: lot reservations
     * on the issued lots first, then item-level ones.
     *
     * @param  list<array{bucket: StockBucket, quantity: string, movement: StockMovement}>  $issued
     */
    private function consumeReservations(Model $demand, int $variantId, ?int $stageId, array $issued): void
    {
        $reservations = $this->openReservations($variantId, $stageId, onlyDemand: $demand)
            ->sortBy(fn (Reservation $r) => $r->lot_id === null ? 1 : 0)->values();

        foreach ($issued as $part) {
            $left = Decimal::of($part['quantity']);

            foreach ($reservations as $reservation) {
                if ($left->isZero()) {
                    break;
                }
                if ($reservation->lot_id !== null && $reservation->lot_id !== $part['bucket']->lotId) {
                    continue;
                }

                $open = Decimal::of($reservation->open());
                if (! $open->isPositive()) {
                    continue;
                }

                $use = BigDecimal::min($open, $left);
                $consumed = Decimal::add($reservation->consumed_quantity, (string) $use, Decimal::QUANTITY);
                $reservation->update([
                    'consumed_quantity' => $consumed,
                    'status' => Decimal::compare($consumed, $reservation->quantity) >= 0 ? Reservation::CONSUMED : Reservation::ACTIVE,
                ]);
                $left = $left->minus($use);
            }
        }
    }

    private function changeBalance(StockBucket $bucket, string $delta): void
    {
        $key = $bucket->key();

        DB::table('stock_balances')->insertOrIgnore([
            'bucket' => $key,
            'item_variant_id' => $bucket->variantId,
            'stage_id' => $bucket->stageId,
            'status' => $bucket->status,
            'location_id' => $bucket->locationId,
            'lot_id' => $bucket->lotId,
            'quantity' => '0',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $balance = StockBalance::query()->where('bucket', $key)->lockForUpdate()->firstOrFail();
        $new = Decimal::add($balance->quantity, $delta, Decimal::QUANTITY);

        if (Decimal::isNegative($new)) {
            $have = Decimal::round($balance->quantity, Decimal::QUANTITY);
            throw new DocumentException('Not enough stock for '.$this->variantLabel($bucket->variantId)." in lot {$this->lotNumber($bucket->lotId)}: have {$have}.");
        }

        $balance->update(['quantity' => $new]);
    }

    private function writeMovement(StockBucket $bucket, string $in, string $out, ?string $unitCost, StockSource $source): StockMovement
    {
        $qty = Decimal::compare($in, '0') > 0 ? $in : $out;

        return StockMovement::query()->create([
            'movement_date' => $source->date->format('Y-m-d'),
            'item_variant_id' => $bucket->variantId,
            'stage_id' => $bucket->stageId,
            'status' => $bucket->status,
            'location_id' => $bucket->locationId,
            'lot_id' => $bucket->lotId,
            'qty_in' => Decimal::round($in, Decimal::QUANTITY),
            'qty_out' => Decimal::round($out, Decimal::QUANTITY),
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost !== null ? Decimal::mul($qty, $unitCost) : null,
            'source_type' => $source->document->getMorphClass(),
            'source_id' => $source->document->getKey(),
            'source_line_id' => $source->lineId,
            'sales_order_line_id' => $source->salesOrderLineId,
            'holder_type' => $source->holder?->getMorphClass(),
            'holder_id' => $source->holder?->getKey(),
            'user_id' => auth()->id(),
            'notes' => $source->notes,
        ]);
    }

    private function assertPositive(string $quantity): void
    {
        if (Decimal::compare($quantity, '0') <= 0) {
            throw new DocumentException('Quantities must be above zero.');
        }
    }

    private function assertTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Stock changes must run inside a database transaction.');
        }
    }

    private function variantLabel(int $variantId): string
    {
        return ItemVariant::withTrashed()->with('item')->find($variantId)?->fullLabel() ?? "variant #{$variantId}";
    }

    private function lotNumber(int $lotId): string
    {
        return (string) Lot::query()->whereKey($lotId)->value('lot_number');
    }
}
