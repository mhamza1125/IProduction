<?php

namespace App\Modules\Production\Services;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Services\BomExplosion;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\LotLink;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcLink;
use App\Modules\Production\Models\PtcStage;
use App\Modules\Production\Models\PtcTransaction;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Services\SupplyLinks;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PTC lifecycle (specification, Module 7): create (from an order line,
 * manually, or as a child), approve (copies route and BOM, creates the output
 * lot), hold/resume, complete, close early, merge.
 */
class Ptcs
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly BomExplosion $explosion,
        private readonly StockLedger $stock,
        private readonly PtcCosting $costing,
        private readonly PtcTransactions $transactions,
        private readonly SupplyLinks $supply,
    ) {}

    /**
     * @param  array<string, mixed>  $data  item_variant_id, planned_qty, route_id, start_stage_id, end_stage_id, sales_order_line_id, parent_id, parent_qty, responsible_employee_id, start_date, target_date, notes
     */
    public function save(?Ptc $ptc, array $data): Ptc
    {
        if ($ptc && ! $ptc->isDraft()) {
            throw new DocumentException("{$ptc->number} is {$ptc->status} and cannot be edited.");
        }

        $variant = ItemVariant::query()->with('item')->findOrFail($data['item_variant_id']);
        if (! $variant->item->can_make) {
            throw new DocumentException("{$variant->item->code} cannot be made.");
        }
        if (! $variant->is_active && ! $ptc) {
            throw new DocumentException("{$variant->sku} is inactive.");
        }
        if (Decimal::compare($data['planned_qty'], '0') <= 0) {
            throw new DocumentException('Planned quantity must be above zero.');
        }

        $route = ProductionRoute::query()->with('stages')->findOrFail($data['route_id']);
        if ($route->item_id !== $variant->item_id) {
            throw new DocumentException('The route belongs to another item.');
        }
        $stageIds = $route->stages->sortBy('sequence')->pluck('stage_id')->values();
        if ($stageIds->isEmpty()) {
            throw new DocumentException("Route {$route->name} has no stages.");
        }
        $start = $data['start_stage_id'] ?? null ?: $stageIds->first();
        $end = $data['end_stage_id'] ?? null ?: $stageIds->last();
        if ($stageIds->search($start) === false || $stageIds->search($end) === false || $stageIds->search($start) > $stageIds->search($end)) {
            throw new DocumentException('Start and end stages must be on the route, start before end.');
        }

        if (! empty($data['sales_order_line_id'])) {
            $line = SalesOrderLine::query()->with('order')->findOrFail($data['sales_order_line_id']);
            if ($line->item_variant_id !== $variant->id) {
                throw new DocumentException('The order line is for another item.');
            }
            if (! $line->order->isOpen()) {
                throw new DocumentException("{$line->order->number} is not open.");
            }
        }

        return DB::transaction(function () use ($ptc, $data, $start, $end) {
            $ptc ??= new Ptc(['number' => $this->numbers->next('ptc', Carbon::parse($data['start_date'])), 'created_by' => auth()->id()]);
            $ptc->fill([
                'item_variant_id' => $data['item_variant_id'],
                'planned_qty' => $data['planned_qty'],
                'route_id' => $data['route_id'],
                'start_stage_id' => $start,
                'end_stage_id' => $end,
                'sales_order_line_id' => $data['sales_order_line_id'] ?? null ?: null,
                'parent_id' => $data['parent_id'] ?? null ?: null,
                'parent_qty' => $data['parent_qty'] ?? null ?: null,
                'responsible_employee_id' => $data['responsible_employee_id'] ?? null ?: null,
                'start_date' => $data['start_date'],
                'target_date' => $data['target_date'] ?? null ?: null,
                'notes' => $data['notes'] ?? null ?: null,
            ])->save();

            return $ptc;
        });
    }

    /** A child PTC for part of the parent's work or demand; same order line. */
    public function createChild(Ptc $parent, string $quantity, ?int $startStageId, bool $required = true): Ptc
    {
        if ($parent->isFinished() || $parent->isDraft()) {
            throw new DocumentException("{$parent->number} must be approved and open to have a child.");
        }

        return DB::transaction(function () use ($parent, $quantity, $startStageId, $required) {
            $child = $this->save(null, [
                'item_variant_id' => $parent->item_variant_id, 'planned_qty' => $quantity, 'route_id' => $parent->route_id,
                'start_stage_id' => $startStageId, 'end_stage_id' => $parent->end_stage_id,
                'sales_order_line_id' => $parent->sales_order_line_id, 'parent_id' => $parent->id, 'parent_qty' => $quantity,
                'start_date' => now()->toDateString(), 'notes' => "Child of {$parent->number}",
            ]);
            $child->forceFill(['required_by_parent' => $required])->save();
            PtcLink::query()->create(['ptc_id' => $child->id, 'source_ptc_id' => $parent->id, 'type' => 'child', 'quantity' => $quantity, 'stage_id' => $startStageId, 'created_by' => auth()->id()]);

            return $child;
        });
    }

    public function approve(Ptc $ptc): Ptc
    {
        return DB::transaction(function () use ($ptc) {
            $ptc = $this->workflow->approve($ptc);
            $ptc->load(['route.stages', 'variant.item']);

            $stages = $ptc->route->stages->sortBy('sequence')->values();
            $from = $stages->search(fn ($s) => $s->stage_id === $ptc->start_stage_id);
            $to = $stages->search(fn ($s) => $s->stage_id === $ptc->end_stage_id);

            foreach ($stages->slice((int) $from, (int) $to - (int) $from + 1)->values() as $i => $rs) {
                PtcStage::query()->create([
                    'ptc_id' => $ptc->id, 'stage_id' => $rs->stage_id, 'sequence' => $i + 1, 'is_optional' => $rs->is_optional,
                    'work_type' => $rs->default_work_type, 'wage_rate' => $rs->wage_rate, 'contractor_rate' => $rs->contractor_rate, 'machine' => $rs->machine,
                ]);
            }

            // BOM copy, per output unit. Starting part-way through, materials of earlier stages are not needed.
            $bom = $ptc->variant->item->activeBom();
            if ($bom !== null) {
                $ptcStageIds = $ptc->stages()->pluck('stage_id')->all();
                $base = '10000';
                foreach ($this->explosion->explode($ptc->variant, $base, false, $bom) as $row) {
                    if ($row['stage_id'] !== null && ! in_array($row['stage_id'], $ptcStageIds, true)) {
                        continue;
                    }
                    $ptc->materials()->create([
                        'item_variant_id' => $row['variant']->id, 'stage_id' => $row['stage_id'],
                        'per_unit' => Decimal::div($row['quantity'], $base, 6), 'wastage_percent' => $row['wastage_percent'], 'is_optional' => $row['optional'],
                    ]);
                }
                $ptc->forceFill(['bom_id' => $bom->id])->saveQuietly();
            }

            $lot = $this->stock->createLot($ptc->item_variant_id, 'made', null, $ptc->start_date, $ptc, ['notes' => "Output of {$ptc->number}"]);
            $ptc->forceFill(['output_lot_id' => $lot->id])->save();

            if ($ptc->sales_order_line_id) {
                $this->supply->link($ptc->orderLine, $ptc, $ptc->item_variant_id, $ptc->planned_qty);
            }

            return $ptc->fresh();
        });
    }

    public function hold(Ptc $ptc, string $reason): void
    {
        $this->setStatus($ptc, ['approved', 'in_progress'], 'on_hold', $reason);
    }

    public function resume(Ptc $ptc): void
    {
        $started = $ptc->transactions()->exists();
        $this->setStatus($ptc, ['on_hold'], $started ? 'in_progress' : 'approved', 'Resumed');
    }

    public function cancel(Ptc $ptc, string $reason): void
    {
        if ($ptc->transactions()->exists()) {
            throw new DocumentException("{$ptc->number} has stage transactions. Close it early instead.");
        }
        DB::transaction(function () use ($ptc, $reason) {
            if ($ptc->isDraft() || $ptc->isApproved()) {
                $this->workflow->cancel($ptc, $reason);
            } else {
                $this->setStatus($ptc, ['on_hold'], 'cancelled', $reason);
            }
            $this->supply->close($ptc);
        });
    }

    /**
     * Completes the PTC once nothing is left in its stages (only rejected
     * pieces may remain, waiting for a decision). Material still held is
     * consumed, and its cost goes onto the output lot.
     */
    public function complete(Ptc $ptc, ?string $overrideReason = null): void
    {
        $this->finish($ptc, 'completed', $overrideReason ?? 'Completed', false, $overrideReason !== null);
    }

    /**
     * Stops work early. Good pieces still at a stage go to Main Store as
     * part-made stock at that stage; held material must be returned or is
     * consumed. The reason says what happens to the rest.
     */
    public function closeEarly(Ptc $ptc, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }
        $this->finish($ptc, 'closed_early', $reason, true, false);
    }

    private function finish(Ptc $ptc, string $status, string $reason, bool $early, bool $override): void
    {
        DB::transaction(function () use ($ptc, $status, $reason, $early, $override) {
            $ptc = Ptc::query()->lockForUpdate()->findOrFail($ptc->id);
            if (! in_array($ptc->status, ['approved', 'in_progress', 'on_hold'], true)) {
                throw new DocumentException("{$ptc->number} is {$ptc->status}.");
            }

            $openChildren = $ptc->children()->where('required_by_parent', true)->whereNotIn('status', Ptc::FINISHED)->pluck('number');
            if ($openChildren->isNotEmpty()) {
                if (! $override || ! auth()->user()?->can('ptcs.override')) {
                    throw new DocumentException('Required child PTCs are still open: '.$openChildren->implode(', ').'.');
                }
            }

            $inProduction = $this->transactions->unitsWithWorkers($ptc);
            if (Decimal::compare($inProduction, '0') > 0) {
                throw new DocumentException("{$inProduction} pieces are still with workers. Receive them first.");
            }

            if ($early) {
                $this->transactions->releaseRemaining($ptc, $reason);
            } elseif (Decimal::compare($this->costing->unitsInPipeline($ptc), '0') > 0) {
                throw new DocumentException('Good pieces are still at a stage. Release them or close the PTC early.');
            }

            $this->transactions->consumeHeld($ptc, now());
            $this->transactions->settleLateCost($ptc);

            $ptc->forceFill(['status' => $status, 'completed_at' => now(), 'close_reason' => $reason === 'Completed' ? null : $reason])->save();
            $this->supply->close($ptc);
            activity('documents')->performedOn($ptc)->event($status)->withProperties(['reason' => $reason])->log(str_replace('_', ' ', ucfirst($status)));
        });
    }

    /**
     * Merges good pieces waiting at one stage from $source into $target.
     * Only PTCs for the same order line (or both without an order), the same
     * variant, route and stage can merge. History stays on both.
     */
    public function merge(Ptc $source, Ptc $target, int $stageId, string $quantity): void
    {
        if ($source->id === $target->id) {
            throw new DocumentException('Choose two different PTCs.');
        }
        if ($source->sales_order_line_id !== $target->sales_order_line_id) {
            throw new DocumentException('PTCs from different orders cannot merge.');
        }
        if ($source->item_variant_id !== $target->item_variant_id || $source->route_id !== $target->route_id) {
            throw new DocumentException('PTCs can only merge with the same variant and route.');
        }
        foreach ([$source, $target] as $p) {
            if (! $p->isActive()) {
                throw new DocumentException("{$p->number} is not active.");
            }
        }

        DB::transaction(function () use ($source, $target, $stageId, $quantity) {
            $this->movePieces($source, $target, $stageId, $quantity, 'merge');
            $source->forceFill(['merged_out_qty' => Decimal::add($source->merged_out_qty, $quantity, 4)])->save();
            $this->supply->move($source, $target, $quantity);
        });
    }

    /** Moves good pieces at a stage from a parent into its child. */
    public function splitToChild(Ptc $parent, Ptc $child, int $stageId, string $quantity): void
    {
        if ($child->parent_id !== $parent->id) {
            throw new DocumentException("{$child->number} is not a child of {$parent->number}.");
        }
        foreach ([$parent, $child] as $p) {
            if (! $p->isActive()) {
                throw new DocumentException("{$p->number} is not active.");
            }
        }

        DB::transaction(function () use ($parent, $child, $stageId, $quantity) {
            $this->movePieces($parent, $child, $stageId, $quantity, 'split');
            $parent->forceFill(['merged_out_qty' => Decimal::add($parent->merged_out_qty, $quantity, 4)])->save();
        });
    }

    private function movePieces(Ptc $from, Ptc $to, int $stageId, string $quantity, string $kind): void
    {
        $fromStage = $from->stages()->where('stage_id', $stageId)->first();
        $toStage = $to->stages()->where('stage_id', $stageId)->first();
        if (! $fromStage || ! $toStage) {
            throw new DocumentException('Both PTCs must have this stage.');
        }

        $this->stock->lockVariant($from->item_variant_id);
        $remaining = Decimal::sub($this->costing->unitsInPipeline($from), $quantity, 4);
        $share = $this->costing->shareFor($from, $quantity, Decimal::isNegative($remaining) ? '0' : $remaining);
        $date = now();
        $source = new StockSource($from, $date, notes: ucfirst($kind)." to {$to->number}");

        $issued = $this->stock->issue($from->item_variant_id, $quantity, $source, $stageId, StockStatus::AVAILABLE, null, null, $from->output_lot_id);
        foreach ($issued as $i) {
            $this->stock->receive(new StockBucket($to->item_variant_id, $stageId, StockStatus::AVAILABLE, $i['bucket']->locationId, (int) $to->output_lot_id), $i['quantity'], new StockSource($to, $date, notes: ucfirst($kind)." from {$from->number}"));
        }
        LotLink::query()->create(['lot_id' => $to->output_lot_id, 'source_lot_id' => $from->output_lot_id, 'quantity' => $quantity, 'document_type' => $to->getMorphClass(), 'document_id' => $to->id]);

        $this->costing->add($from, 'transfer_out', '-'.$share, $to, ucfirst($kind)." to {$to->number}");
        $this->costing->add($to, 'transfer_in', $share, $from, ucfirst($kind)." from {$from->number}");

        PtcTransaction::query()->create(['ptc_id' => $from->id, 'ptc_stage_id' => $fromStage->id, 'type' => "{$kind}_out", 'transaction_date' => $date, 'item_variant_id' => $from->item_variant_id,
            'lot_id' => $from->output_lot_id, 'quantity' => $quantity, 'amount' => $share, 'related_ptc_id' => $to->id, 'created_by' => auth()->id()]);
        PtcTransaction::query()->create(['ptc_id' => $to->id, 'ptc_stage_id' => $toStage->id, 'type' => "{$kind}_in", 'transaction_date' => $date, 'item_variant_id' => $to->item_variant_id,
            'lot_id' => $to->output_lot_id, 'quantity' => $quantity, 'amount' => $share, 'related_ptc_id' => $from->id, 'created_by' => auth()->id()]);

        PtcLink::query()->create(['ptc_id' => $to->id, 'source_ptc_id' => $from->id, 'type' => $kind, 'quantity' => $quantity, 'stage_id' => $stageId, 'created_by' => auth()->id()]);
        if ($to->status === 'approved') {
            $to->forceFill(['status' => 'in_progress'])->save();
        }
    }

    /** @param  list<string>  $from */
    private function setStatus(Ptc $ptc, array $from, string $to, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }
        DB::transaction(function () use ($ptc, $from, $to, $reason) {
            $locked = Ptc::query()->lockForUpdate()->findOrFail($ptc->id);
            if (! in_array($locked->status, $from, true)) {
                throw new DocumentException("{$locked->number} is {$locked->status}.");
            }
            $locked->forceFill(['status' => $to] + ($to === 'cancelled' ? ['cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'cancel_reason' => $reason] : []))->save();
            activity('documents')->performedOn($locked)->event($to)->withProperties(['reason' => $reason])->log(ucfirst(str_replace('_', ' ', $to)));
        });
    }
}
