<?php

namespace App\Modules\Production\Services;

use App\Modules\Catalog\Models\RouteStage;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\LotLink;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcStage;
use App\Modules\Production\Models\PtcTransaction;
use App\Modules\Sales\Services\SupplyLinks;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\PeriodLock;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Stage transactions (specification, Module 7). Each one runs in a single
 * transaction with the PTC row locked, writes stock only through StockLedger
 * and is recorded as an immutable PtcTransaction.
 *
 * Product pieces of a PTC live in its output lot: In Production at a stage
 * while a worker has them, Available at a stage when received, Rejected at
 * the stage where they were found. Material issued is In Production at the
 * worker's location until it is consumed, returned or wasted.
 */
class PtcTransactions
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly PtcCosting $costing,
        private readonly ProductionAccounting $accounting,
        private readonly WageWorks $wages,
        private readonly Rejections $rejections,
        private readonly SupplyLinks $supply,
        private readonly PeriodLock $periodLock,
    ) {}

    /**
     * Material Issue (or Extra Issue, with a reason, during an active stage):
     * Available → In Production at the worker.
     */
    public function issueMaterial(Ptc $ptc, ?PtcStage $stage, int $variantId, string $quantity, Worker $worker, CarbonInterface $date, bool $extra = false, ?string $reason = null, ?int $lotId = null): void
    {
        if ($variantId === $ptc->item_variant_id) {
            throw new DocumentException('Issue product pieces with a Stage Issue.');
        }
        if ($extra && trim((string) $reason) === '') {
            throw new DocumentException('Extra issues need a reason.');
        }

        $this->run($ptc, $date, function (Ptc $ptc) use ($stage, $variantId, $quantity, $worker, $date, $extra, $reason, $lotId) {
            $location = $worker->location();
            $source = new StockSource($ptc, $date, salesOrderLineId: $ptc->sales_order_line_id, holder: $worker->model, notes: $extra ? "Extra issue: {$reason}" : null);

            foreach ($this->stock->allocate($variantId, $quantity, null, StockStatus::AVAILABLE, null, null, $lotId) as $a) {
                $this->stock->transfer($a['bucket'], $a['bucket']->with(StockStatus::IN_PRODUCTION, $location->id), $a['quantity'], $source);
                $cost = Lot::query()->whereKey($a['bucket']->lotId)->value('unit_cost');
                $amount = $cost === null ? null : Decimal::mul($a['quantity'], $cost);
                $this->record($ptc, $stage, $extra ? 'extra_issue' : 'material_issue', $date, $worker, $variantId, $a['bucket']->lotId, $location->id, $a['quantity'], $cost, $amount, $reason);
                if ($amount !== null) {
                    $this->costing->add($ptc, $extra ? 'extra_material' : 'material', $amount, $ptc, "Issued lot {$a['bucket']->lotId}");
                }
            }
        });
    }

    /**
     * Stage Issue: pieces from the previous stage's Available stock (of this
     * PTC) to In Production with the worker. At the PTC's first stage the
     * pieces are made from material, so nothing moves yet; or, when the PTC
     * starts part-way through the route, they come from stock of this
     * variant at the previous route stage (e.g. bought part-made).
     */
    public function issueStage(Ptc $ptc, PtcStage $stage, string $quantity, Worker $worker, CarbonInterface $date, bool $fromStock = false, ?int $lotId = null): void
    {
        $this->run($ptc, $date, function (Ptc $ptc) use ($stage, $quantity, $worker, $date, $fromStock, $lotId) {
            $stage = PtcStage::query()->lockForUpdate()->findOrFail($stage->id);
            if ($stage->is_skipped) {
                throw new DocumentException("{$stage->stage->name} was skipped.");
            }
            $location = $worker->location();
            $source = new StockSource($ptc, $date, salesOrderLineId: $ptc->sales_order_line_id, holder: $worker->model);
            $previous = $this->previousStage($ptc, $stage);

            if ($previous !== null) {
                $available = $this->stock->onHand($ptc->item_variant_id, $previous->stage_id, StockStatus::AVAILABLE, null, $ptc->output_lot_id);
                if (Decimal::compare($quantity, $available) > 0) {
                    throw new DocumentException("Only {$available} pieces are available after {$previous->stage->name}.");
                }
                foreach ($this->stock->allocate($ptc->item_variant_id, $quantity, $previous->stage_id, StockStatus::AVAILABLE, null, null, $ptc->output_lot_id) as $a) {
                    $this->stock->remove($a['bucket'], $a['quantity'], $source);
                    $this->stock->receive(new StockBucket($ptc->item_variant_id, $stage->stage_id, StockStatus::IN_PRODUCTION, $location->id, (int) $ptc->output_lot_id), $a['quantity'], $source);
                }
            } elseif ($fromStock || $this->startsPartWay($ptc)) {
                $routeStageId = $this->routeStageBefore($ptc);
                foreach ($this->stock->allocate($ptc->item_variant_id, $quantity, $routeStageId, StockStatus::AVAILABLE, null, null, $lotId) as $a) {
                    if ($a['bucket']->lotId === $ptc->output_lot_id) {
                        throw new DocumentException('Pieces of this PTC cannot enter it again.');
                    }
                    $cost = Lot::query()->whereKey($a['bucket']->lotId)->value('unit_cost');
                    $this->stock->remove($a['bucket'], $a['quantity'], $source);
                    $this->stock->receive(new StockBucket($ptc->item_variant_id, $stage->stage_id, StockStatus::IN_PRODUCTION, $location->id, (int) $ptc->output_lot_id), $a['quantity'], $source);
                    LotLink::query()->create(['lot_id' => $ptc->output_lot_id, 'source_lot_id' => $a['bucket']->lotId, 'quantity' => $a['quantity'], 'document_type' => $ptc->getMorphClass(), 'document_id' => $ptc->id]);
                    if ($cost !== null) {
                        $this->costing->add($ptc, 'stock_in', Decimal::mul($a['quantity'], $cost), $ptc, "Stock lot {$a['bucket']->lotId} entered");
                    }
                }
            }

            $stage->update(['issued_qty' => Decimal::add($stage->issued_qty, $quantity, 4)]);
            $this->record($ptc, $stage, 'stage_issue', $date, $worker, $ptc->item_variant_id, $ptc->output_lot_id, $location->id, $quantity);
        });
    }

    /**
     * Stage Receive: good pieces become Available at the stage; rejected
     * pieces go to Rejected at the stage. Received + rejected cannot exceed
     * what the worker still has, unless over-output is approved (first stage
     * only, needs ptcs.override and a reason; the extra goes to free stock).
     * A wage employee's accepted pieces create wage work at the route rate;
     * a contractor's accepted pieces post the contractor payable.
     */
    public function receiveStage(Ptc $ptc, PtcStage $stage, Worker $worker, string $good, string $rejected, CarbonInterface $date, ?string $rejectReason = null, ?string $overOutputReason = null): void
    {
        if (Decimal::compare(Decimal::add($good, $rejected, 4), '0') <= 0) {
            throw new DocumentException('Enter the good or rejected quantity.');
        }
        if (Decimal::compare($rejected, '0') > 0 && trim((string) $rejectReason) === '') {
            throw new DocumentException('Give the rejection reason.');
        }

        $this->run($ptc, $date, function (Ptc $ptc) use ($stage, $worker, $good, $rejected, $date, $rejectReason, $overOutputReason) {
            $stage = PtcStage::query()->with('stage')->lockForUpdate()->findOrFail($stage->id);
            $total = Decimal::add($good, $rejected, 4);
            $pending = $this->pendingWith($ptc, $stage, $worker);
            $floor = Location::productionFloor();
            $source = new StockSource($ptc, $date, salesOrderLineId: $ptc->sales_order_line_id, holder: $worker->model);

            $over = Decimal::sub($total, $pending, 4);
            if (Decimal::compare($over, '0') > 0) {
                if ($this->previousStage($ptc, $stage) !== null || $this->startsPartWay($ptc)) {
                    throw new DocumentException("{$worker->model->name} has only {$pending} pieces at {$stage->stage->name}.");
                }
                if (trim((string) $overOutputReason) === '' || ! auth()->user()?->can('ptcs.override')) {
                    throw new DocumentException("Received + rejected ({$total}) is more than the {$pending} issued. Over-output needs approval and a reason.");
                }
            }

            // Pieces the worker physically holds (issued from a stage or from stock).
            $held = $this->stock->onHand($ptc->item_variant_id, $stage->stage_id, StockStatus::IN_PRODUCTION, $worker->location()->id, $ptc->output_lot_id);
            $fromHeld = BigDecimal::min(Decimal::of($held), Decimal::of($total));
            $made = Decimal::of($total)->minus($fromHeld);

            $parts = ['good' => Decimal::of($good), 'rejected' => Decimal::of($rejected)];
            $heldLeft = $fromHeld;
            foreach ($parts as $kind => $qty) {
                if (! $qty->isPositive()) {
                    continue;
                }
                $status = $kind === 'good' ? StockStatus::AVAILABLE : StockStatus::REJECTED;
                $to = new StockBucket($ptc->item_variant_id, $stage->stage_id, $status, $floor->id, (int) $ptc->output_lot_id);
                $take = BigDecimal::min($heldLeft, $qty);
                if ($take->isPositive()) {
                    $this->stock->transfer(new StockBucket($ptc->item_variant_id, $stage->stage_id, StockStatus::IN_PRODUCTION, $worker->location()->id, (int) $ptc->output_lot_id), $to, (string) $take->toScale(4), $source);
                    $heldLeft = $heldLeft->minus($take);
                }
                $create = $qty->minus($take);
                if ($create->isPositive()) {
                    $this->stock->receive($to, (string) $create->toScale(4), $source);
                }
            }
            unset($made);

            $stage->update([
                'received_qty' => Decimal::add($stage->received_qty, $good, 4),
                'rejected_qty' => Decimal::add($stage->rejected_qty, $rejected, 4),
                'issued_qty' => Decimal::compare($over, '0') > 0 ? Decimal::add($stage->issued_qty, $over, 4) : $stage->issued_qty,
            ]);

            $receive = Decimal::compare($good, '0') > 0
                ? $this->record($ptc, $stage, 'stage_receive', $date, $worker, $ptc->item_variant_id, $ptc->output_lot_id, $floor->id, $good, reason: Decimal::compare($over, '0') > 0 ? "Over-output {$over}: {$overOutputReason}" : null)
                : null;

            if (Decimal::compare($rejected, '0') > 0) {
                $tx = $this->record($ptc, $stage, 'reject', $date, $worker, $ptc->item_variant_id, $ptc->output_lot_id, $floor->id, $rejected, reason: $rejectReason);
                $this->rejections->record($tx, [
                    'rejection_date' => $date, 'ptc_id' => $ptc->id, 'item_variant_id' => $ptc->item_variant_id, 'stage_id' => $stage->stage_id,
                    'lot_id' => $ptc->output_lot_id, 'location_id' => $floor->id, 'quantity' => $rejected, 'reason' => (string) $rejectReason,
                    'responsible_type' => $worker->model->getMorphClass(), 'responsible_id' => $worker->model->id,
                ]);
            }

            if ($receive !== null && $worker->model instanceof Employee && $worker->model->employee_type === Employee::TYPE_WAGE && $stage->wage_rate !== null) {
                $this->wages->record($worker->model, $ptc, $stage, $receive, $good, $rejected, $stage->wage_rate, $date);
            }
            if ($receive !== null && $worker->isContractor() && $stage->contractor_rate !== null) {
                $amount = Decimal::mul($good, $stage->contractor_rate);
                $this->accounting->labor($worker->model, $amount, $date, $ptc, "{$ptc->number} {$stage->stage->name}: {$good} accepted × {$stage->contractor_rate}");
                $this->costing->add($ptc, 'contractor', $amount, $receive, "{$worker->model->name} at {$stage->stage->name}");
            }
        });
    }

    /** Pieces found faulty while waiting at a stage: Available → Rejected. */
    public function rejectAtStage(Ptc $ptc, PtcStage $stage, string $quantity, string $reason, CarbonInterface $date, ?string $foundBy = null): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('Give the rejection reason.');
        }
        $this->run($ptc, $date, function (Ptc $ptc) use ($stage, $quantity, $reason, $date, $foundBy) {
            $source = new StockSource($ptc, $date, salesOrderLineId: $ptc->sales_order_line_id, notes: $reason);
            foreach ($this->stock->allocate($ptc->item_variant_id, $quantity, $stage->stage_id, StockStatus::AVAILABLE, null, null, $ptc->output_lot_id) as $a) {
                $this->stock->transfer($a['bucket'], $a['bucket']->with(StockStatus::REJECTED), $a['quantity'], $source);
                $tx = $this->record($ptc, $stage, 'reject', $date, null, $ptc->item_variant_id, $ptc->output_lot_id, $a['bucket']->locationId, $a['quantity'], reason: $reason);
                $this->rejections->record($tx, ['rejection_date' => $date, 'ptc_id' => $ptc->id, 'item_variant_id' => $ptc->item_variant_id, 'stage_id' => $stage->stage_id,
                    'lot_id' => $ptc->output_lot_id, 'location_id' => $a['bucket']->locationId, 'quantity' => $a['quantity'], 'reason' => $reason, 'found_by' => $foundBy]);
            }
            $stage->update(['rejected_qty' => Decimal::add($stage->rejected_qty, $quantity, 4)]);
        });
    }

    /** Unused material held for the PTC back to Main Store. */
    public function returnToStore(Ptc $ptc, int $variantId, int $lotId, int $locationId, string $quantity, CarbonInterface $date, ?string $reason = null): void
    {
        $this->run($ptc, $date, function (Ptc $ptc) use ($variantId, $lotId, $locationId, $quantity, $date, $reason) {
            $this->assertHeld($ptc, $variantId, $lotId, $locationId, $quantity);
            $from = new StockBucket($variantId, null, StockStatus::IN_PRODUCTION, $locationId, $lotId);
            $this->stock->transfer($from, $from->with(StockStatus::AVAILABLE, Location::mainStore()->id), $quantity, new StockSource($ptc, $date, notes: $reason));
            $cost = Lot::query()->whereKey($lotId)->value('unit_cost');
            $amount = $cost === null ? null : Decimal::mul($quantity, $cost);
            $this->record($ptc, null, 'return_to_store', $date, null, $variantId, $lotId, $locationId, $quantity, $cost, $amount, $reason);
            if ($amount !== null) {
                $this->costing->add($ptc, 'material', '-'.$amount, $ptc, 'Returned to store');
            }
        });
    }

    /** Unusable material: stock out, Dr Production Loss, Cr Inventory. */
    public function wastage(Ptc $ptc, int $variantId, int $lotId, int $locationId, string $quantity, string $reason, CarbonInterface $date): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('Give the wastage reason.');
        }
        $this->run($ptc, $date, function (Ptc $ptc) use ($variantId, $lotId, $locationId, $quantity, $reason, $date) {
            $this->assertHeld($ptc, $variantId, $lotId, $locationId, $quantity);
            $this->stock->remove(new StockBucket($variantId, null, StockStatus::IN_PRODUCTION, $locationId, $lotId), $quantity, new StockSource($ptc, $date, notes: "Wastage: {$reason}"));
            $cost = Lot::query()->whereKey($lotId)->value('unit_cost');
            $amount = $cost === null ? '0' : Decimal::mul($quantity, $cost);
            $this->record($ptc, null, 'wastage', $date, null, $variantId, $lotId, $locationId, $quantity, $cost, $amount, $reason);
            $this->costing->add($ptc, 'material', '-'.$amount, $ptc, "Wastage: {$reason}");
            $this->accounting->loss($amount, $date, $ptc, "{$ptc->number} wastage: {$reason}");
        });
    }

    /** Skips an optional stage (with a reason) before any work happened there. */
    public function skipStage(Ptc $ptc, PtcStage $stage, string $reason, CarbonInterface $date): void
    {
        if (! $stage->is_optional) {
            throw new DocumentException("{$stage->stage->name} is mandatory and cannot be skipped.");
        }
        if (trim($reason) === '') {
            throw new DocumentException('Give the reason for skipping.');
        }
        $this->run($ptc, $date, function (Ptc $ptc) use ($stage, $reason, $date) {
            if (Decimal::compare($stage->issued_qty, '0') > 0) {
                throw new DocumentException('Work has already been issued at this stage.');
            }
            $stage->update(['is_skipped' => true, 'skip_reason' => $reason]);
            $this->record($ptc, $stage, 'skip', $date, null, $ptc->item_variant_id, null, null, '0', reason: $reason);
        });
    }

    /**
     * Release: final-stage pieces to Main Store (as finished goods, or
     * part-made at the end stage when the PTC stops before the route ends).
     * Uses the BOM material for these pieces and moves their cost share onto
     * the output lot; reserved to the order line when there is one.
     */
    public function release(Ptc $ptc, string $quantity, CarbonInterface $date): void
    {
        $this->run($ptc, $date, function (Ptc $ptc) use ($quantity, $date) {
            $last = $ptc->stages()->where('is_skipped', false)->reorder('sequence', 'desc')->firstOrFail();
            $available = $this->stock->onHand($ptc->item_variant_id, $last->stage_id, StockStatus::AVAILABLE, null, $ptc->output_lot_id);
            if (Decimal::compare($quantity, $available) > 0) {
                throw new DocumentException("Only {$available} pieces are ready at {$last->stage->name}.");
            }
            $this->moveToStore($ptc, $last, $quantity, $date, null);
        });
    }

    /** Close early: every good piece still at a stage goes to Main Store at that stage. */
    public function releaseRemaining(Ptc $ptc, string $reason): void
    {
        $date = now();
        foreach ($ptc->stages()->with('stage')->get()->reverse() as $stage) {
            $qty = $this->stock->onHand($ptc->item_variant_id, $stage->stage_id, StockStatus::AVAILABLE, null, $ptc->output_lot_id);
            if (Decimal::compare($qty, '0') > 0) {
                $this->moveToStore($ptc, $stage, $qty, $date, "Closed early: {$reason}");
            }
        }
    }

    private function moveToStore(Ptc $ptc, PtcStage $stage, string $quantity, CarbonInterface $date, ?string $note): void
    {
        $this->consumeFor($ptc, $quantity, $date);

        $remainingPipeline = Decimal::sub($this->costing->unitsInPipeline($ptc), $quantity, 4);
        // Pieces still expected from this PTC; rejected pieces are allowed loss, so their cost stays with the good ones.
        $rejected = Decimal::sum($ptc->stages()->pluck('rejected_qty'), 4);
        $stillToMake = Decimal::sub(Decimal::sub(Decimal::sub($ptc->plannedOpen(), $ptc->released_qty, 4), $rejected, 4), $quantity, 4);
        $remaining = Decimal::compare($stillToMake, $remainingPipeline) > 0 ? $stillToMake : $remainingPipeline;
        $remaining = Decimal::isNegative($remaining) ? '0' : $remaining;
        $share = $this->costing->shareFor($ptc, $quantity, $remaining);

        // The lot's cost is the average of everything released from it.
        $lot = Lot::query()->lockForUpdate()->findOrFail($ptc->output_lot_id);
        $releasedCost = Decimal::mul($ptc->released_qty, $lot->unit_cost ?? '0');
        $newQty = Decimal::add($ptc->released_qty, $quantity, 4);
        $lot->update(['unit_cost' => Decimal::div(Decimal::add($releasedCost, $share), $newQty, 4)]);

        $isRouteEnd = $stage->stage_id === RouteStage::query()->where('route_id', $ptc->route_id)->orderByDesc('sequence')->value('stage_id');
        $targetStage = $isRouteEnd ? null : $stage->stage_id;
        $source = new StockSource($ptc, $date, salesOrderLineId: $ptc->sales_order_line_id, notes: $note);
        $main = Location::mainStore();
        foreach ($this->stock->allocate($ptc->item_variant_id, $quantity, $stage->stage_id, StockStatus::AVAILABLE, null, null, $ptc->output_lot_id) as $a) {
            $this->stock->transfer($a['bucket'], $a['bucket']->with(StockStatus::AVAILABLE, $main->id, $targetStage, $targetStage === null), $a['quantity'], $source);
        }

        $this->costing->add($ptc, 'released', '-'.$share, $ptc, "Released {$quantity}");
        $ptc->forceFill(['released_qty' => $newQty])->save();
        $this->record($ptc, $stage, 'release', $date, null, $ptc->item_variant_id, $ptc->output_lot_id, $main->id, $quantity, Decimal::div($share, $quantity, 4), $share, $note);

        $this->supply->fulfil($ptc, $quantity, (int) $ptc->output_lot_id, $targetStage);
    }

    /** Uses BOM material for released pieces from what the PTC holds. */
    private function consumeFor(Ptc $ptc, string $quantity, CarbonInterface $date): void
    {
        $held = $this->costing->heldMaterial($ptc)->groupBy('item_variant_id');
        foreach ($ptc->materials()->get() as $material) {
            $need = Decimal::of($material->per_unit)->multipliedBy($quantity)->toScale(4, RoundingMode::HalfUp);
            foreach ($held->get($material->item_variant_id, collect()) as $h) {
                if (! $need->isPositive()) {
                    break;
                }
                $take = BigDecimal::min($need, Decimal::of($h['quantity']));
                $this->consume($ptc, $h, (string) $take->toScale(4), $date);
                $need = $need->minus($take);
            }
        }
    }

    /** On completion, whatever material is still held counts as used. */
    public function consumeHeld(Ptc $ptc, CarbonInterface $date): void
    {
        foreach ($this->costing->heldMaterial($ptc) as $h) {
            $this->consume($ptc, $h, $h['quantity'], $date);
        }
    }

    /**
     * Cost that arrives after the last release (late wage approvals, final
     * material use) is added to the output lot's cost; with nothing produced
     * it is written off to Production Loss.
     */
    public function settleLateCost(Ptc $ptc): void
    {
        $pool = $this->costing->pool($ptc);
        if (Decimal::isZero($pool)) {
            return;
        }
        $ptc->refresh();
        if (Decimal::compare($ptc->released_qty, '0') > 0) {
            $lot = Lot::query()->lockForUpdate()->findOrFail($ptc->output_lot_id);
            $total = Decimal::add(Decimal::mul($ptc->released_qty, $lot->unit_cost ?? '0'), $pool);
            $lot->update(['unit_cost' => Decimal::div($total, $ptc->released_qty, 4)]);
            $this->costing->add($ptc, 'released', Decimal::sub('0', $pool), $ptc, 'Final cost added to output lot');

            return;
        }
        $this->accounting->loss($pool, now(), $ptc, "{$ptc->number} closed with nothing produced");
        $this->costing->add($ptc, 'released', Decimal::sub('0', $pool), $ptc, 'Written off to Production Loss');
    }

    /** Pieces currently with workers (In Production at any PTC stage). */
    public function unitsWithWorkers(Ptc $ptc): string
    {
        return Decimal::sum(StockBalance::query()->where('lot_id', $ptc->output_lot_id)->where('status', StockStatus::IN_PRODUCTION)->pluck('quantity'), 4);
    }

    /**
     * What a worker still has at a stage: issued to them − received − rejected from them.
     */
    public function pendingWith(Ptc $ptc, PtcStage $stage, Worker $worker): string
    {
        $col = $worker->isContractor() ? 'contractor_id' : 'employee_id';
        $rows = PtcTransaction::query()->where('ptc_id', $ptc->id)->where('ptc_stage_id', $stage->id)->where($col, $worker->model->id)
            ->whereIn('type', ['stage_issue', 'stage_receive', 'reject'])->get();
        $issued = Decimal::sum($rows->where('type', 'stage_issue')->pluck('quantity'), 4);
        $back = Decimal::sum($rows->whereIn('type', ['stage_receive', 'reject'])->pluck('quantity'), 4);

        return Decimal::sub($issued, $back, 4);
    }

    /**
     * Who holds what at each stage.
     *
     * @return list<array{stage_id: int, worker: string, pending: string}>
     */
    public function holders(Ptc $ptc): array
    {
        $out = [];
        $rows = PtcTransaction::query()->with(['employee', 'contractor', 'ptcStage'])->where('ptc_id', $ptc->id)
            ->whereIn('type', ['stage_issue', 'stage_receive', 'reject'])->where(fn ($q) => $q->whereNotNull('employee_id')->orWhereNotNull('contractor_id'))->get()
            ->groupBy(fn ($t) => $t->ptc_stage_id.'-'.($t->employee_id ? 'e'.$t->employee_id : 'c'.$t->contractor_id));
        foreach ($rows as $group) {
            $issued = Decimal::sum($group->where('type', 'stage_issue')->pluck('quantity'), 4);
            $back = Decimal::sum($group->whereIn('type', ['stage_receive', 'reject'])->pluck('quantity'), 4);
            $pending = Decimal::sub($issued, $back, 4);
            if (Decimal::compare($pending, '0') > 0) {
                $out[] = ['stage_id' => (int) $group->first()->ptcStage?->stage_id, 'worker' => (string) $group->first()->workerName(), 'pending' => $pending];
            }
        }

        return $out;
    }

    /** @param  array{item_variant_id: int, lot_id: int, location_id: int, quantity: string}  $held */
    private function consume(Ptc $ptc, array $held, string $quantity, CarbonInterface $date): void
    {
        if (Decimal::compare($quantity, '0') <= 0) {
            return;
        }
        $this->stock->remove(new StockBucket($held['item_variant_id'], null, StockStatus::IN_PRODUCTION, $held['location_id'], $held['lot_id']), $quantity, new StockSource($ptc, $date, notes: 'Used in production'));
        LotLink::query()->firstOrCreate(
            ['lot_id' => $ptc->output_lot_id, 'source_lot_id' => $held['lot_id'], 'document_type' => $ptc->getMorphClass(), 'document_id' => $ptc->id],
            ['quantity' => null],
        );
        $this->record($ptc, null, 'consume', $date, null, $held['item_variant_id'], $held['lot_id'], $held['location_id'], $quantity);
    }

    private function assertHeld(Ptc $ptc, int $variantId, int $lotId, int $locationId, string $quantity): void
    {
        $held = $this->costing->heldMaterial($ptc)->get("{$variantId}-{$lotId}-{$locationId}")['quantity'] ?? '0';
        if (Decimal::compare($quantity, $held) > 0) {
            throw new DocumentException("Only {$held} of this material lot is held for {$ptc->number}.");
        }
    }

    private function previousStage(Ptc $ptc, PtcStage $stage): ?PtcStage
    {
        return $ptc->stages()->with('stage')->where('sequence', '<', $stage->sequence)->where('is_skipped', false)->reorder('sequence', 'desc')->first();
    }

    private function startsPartWay(Ptc $ptc): bool
    {
        return $this->routeStageBefore($ptc) !== null;
    }

    /** The route stage just before the PTC's first stage, if the PTC starts part-way. */
    private function routeStageBefore(Ptc $ptc): ?int
    {
        $stages = RouteStage::query()->where('route_id', $ptc->route_id)->orderBy('sequence')->pluck('stage_id')->values();
        $i = $stages->search($ptc->stages()->orderBy('sequence')->value('stage_id'));

        return $i > 0 ? (int) $stages[$i - 1] : null;
    }

    private function record(Ptc $ptc, ?PtcStage $stage, string $type, CarbonInterface $date, ?Worker $worker, int $variantId, ?int $lotId, ?int $locationId, string $quantity, ?string $unitCost = null, ?string $amount = null, ?string $reason = null): PtcTransaction
    {
        return PtcTransaction::query()->create(($worker?->columns() ?? []) + [
            'ptc_id' => $ptc->id, 'ptc_stage_id' => $stage?->id, 'type' => $type, 'transaction_date' => $date->format('Y-m-d'),
            'item_variant_id' => $variantId, 'lot_id' => $lotId, 'location_id' => $locationId, 'quantity' => $quantity,
            'unit_cost' => $unitCost, 'amount' => $amount, 'reason' => $reason, 'created_by' => auth()->id(),
        ]);
    }

    /**
     * Locks the PTC, checks it is active and the date is open, runs the change,
     * and moves an approved PTC to In Progress.
     *
     * @param  callable(Ptc): void  $change
     */
    private function run(Ptc $ptc, CarbonInterface $date, callable $change): void
    {
        DB::transaction(function () use ($ptc, $date, $change) {
            $locked = Ptc::query()->lockForUpdate()->findOrFail($ptc->id);
            if (! $locked->isActive()) {
                throw new DocumentException("{$locked->number} is ".str_replace('_', ' ', $locked->status).'; no transactions can be recorded.');
            }
            $this->periodLock->assertOpen(Carbon::instance($date), null, $locked);
            $change($locked);
            if ($locked->status === 'approved') {
                $locked->forceFill(['status' => 'in_progress'])->save();
            }
        });
        $ptc->refresh();
    }
}
