<?php

namespace App\Modules\Production\Services;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Core\Services\Settings;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\LotLink;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Contractor;
use App\Modules\Production\Models\ContractorJob;
use App\Modules\Production\Models\ContractorJobLine;
use App\Modules\Production\Models\ContractorJobTransaction;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Services\SupplyLinks;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use App\Support\Documents\PeriodLock;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contractor jobs (specification, Module 8). Material sent to a contractor
 * stays company stock, In Production at the contractor's location. Received
 * output: accepted pieces into stock (a Contractor Made lot), rejected pieces
 * to Rejected; the payable for paid pieces × agreed rate posts
 * Dr Inventory, Cr Contractor Payable. Over-receipts use the purchasing
 * options: accept and pay, accept as free, or return the extra.
 */
class ContractorJobs
{
    public const OVER_RECEIPT_ACTIONS = ['accept_pay' => 'Accept and pay', 'accept_free' => 'Accept as free', 'return_extra' => 'Return the extra'];

    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly StockLedger $stock,
        private readonly ProductionAccounting $accounting,
        private readonly Rejections $rejections,
        private readonly SupplyLinks $supply,
        private readonly Settings $settings,
        private readonly PeriodLock $periodLock,
    ) {}

    /**
     * @param  array<string, mixed>  $header  contractor_id, sales_order_line_id, job_date, due_date, payment_basis, notes
     * @param  list<array<string, mixed>>  $lines  item_variant_id, stage_id, quantity, rate
     * @param  list<array<string, mixed>>  $materials  item_variant_id, expected_qty
     */
    public function save(?ContractorJob $job, array $header, array $lines, array $materials): ContractorJob
    {
        if ($job && ! $job->isDraft()) {
            throw new DocumentException("{$job->number} is {$job->status} and cannot be edited.");
        }
        $contractor = Contractor::query()->findOrFail($header['contractor_id']);
        if (! $contractor->is_active && (! $job || $job->contractor_id !== $contractor->id)) {
            throw new DocumentException("{$contractor->name} is inactive.");
        }
        if ($lines === []) {
            throw new DocumentException('Add at least one output line.');
        }
        foreach ($lines as $l) {
            if (Decimal::compare((string) $l['quantity'], '0') <= 0 || Decimal::isNegative((string) $l['rate'])) {
                throw new DocumentException('Quantities must be above zero and rates cannot be negative.');
            }
        }

        return DB::transaction(function () use ($job, $header, $lines, $materials) {
            $job ??= new ContractorJob(['number' => $this->numbers->next('contractor_job', Carbon::parse($header['job_date'])), 'created_by' => auth()->id()]);
            $job->fill([
                'contractor_id' => $header['contractor_id'], 'sales_order_line_id' => $header['sales_order_line_id'] ?? null ?: null,
                'job_date' => $header['job_date'], 'due_date' => $header['due_date'] ?? null ?: null,
                'payment_basis' => $header['payment_basis'] ?? 'per_piece', 'notes' => $header['notes'] ?? null ?: null,
            ])->save();
            $job->lines()->delete();
            $job->materials()->delete();
            foreach ($lines as $i => $l) {
                $job->lines()->create(['item_variant_id' => $l['item_variant_id'], 'stage_id' => $l['stage_id'] ?? null ?: null, 'quantity' => $l['quantity'], 'rate' => $l['rate'], 'sort_order' => $i]);
            }
            foreach ($materials as $m) {
                if (Decimal::compare((string) $m['expected_qty'], '0') > 0) {
                    $job->materials()->create(['item_variant_id' => $m['item_variant_id'], 'expected_qty' => $m['expected_qty']]);
                }
            }

            return $job;
        });
    }

    public function approve(ContractorJob $job): ContractorJob
    {
        return DB::transaction(function () use ($job) {
            $job = $this->workflow->approve($job);
            if ($job->sales_order_line_id) {
                $line = SalesOrderLine::query()->findOrFail($job->sales_order_line_id);
                foreach ($job->lines as $l) {
                    if ($l->item_variant_id === $line->item_variant_id && $l->stage_id === null) {
                        $this->supply->link($line, $l, $l->item_variant_id, $l->quantity);
                    }
                }
            }

            return $job;
        });
    }

    public function issueMaterial(ContractorJob $job, int $variantId, string $quantity, CarbonInterface $date, ?int $lotId = null): void
    {
        $this->run($job, $date, function (ContractorJob $job) use ($variantId, $quantity, $date, $lotId) {
            $location = Location::forContractor($job->contractor);
            $source = new StockSource($job, $date, holder: $job->contractor);
            foreach ($this->stock->allocate($variantId, $quantity, null, StockStatus::AVAILABLE, null, null, $lotId) as $a) {
                if ($a['bucket']->locationId === $location->id) {
                    continue;
                }
                $this->stock->transfer($a['bucket'], $a['bucket']->with(StockStatus::IN_PRODUCTION, $location->id), $a['quantity'], $source);
                $cost = Lot::query()->whereKey($a['bucket']->lotId)->value('unit_cost');
                $this->record($job, null, 'issue', $date, $variantId, $a['bucket']->lotId, $a['quantity'], $cost);
            }
            if ($job->status === 'approved') {
                $job->forceFill(['status' => 'material_issued'])->save();
            }
        });
    }

    public function returnMaterial(ContractorJob $job, int $variantId, int $lotId, string $quantity, CarbonInterface $date, ?string $reason = null): void
    {
        $this->run($job, $date, function (ContractorJob $job) use ($variantId, $lotId, $quantity, $date, $reason) {
            $this->assertHeld($job, $variantId, $lotId, $quantity);
            $from = new StockBucket($variantId, null, StockStatus::IN_PRODUCTION, Location::forContractor($job->contractor)->id, $lotId);
            $this->stock->transfer($from, $from->with(StockStatus::AVAILABLE, Location::mainStore()->id), $quantity, new StockSource($job, $date, notes: $reason));
            $this->record($job, null, 'return', $date, $variantId, $lotId, $quantity, Lot::query()->whereKey($lotId)->value('unit_cost'), $reason);
        });
    }

    public function wastage(ContractorJob $job, int $variantId, int $lotId, string $quantity, string $reason, CarbonInterface $date): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('Give the wastage reason.');
        }
        $this->run($job, $date, function (ContractorJob $job) use ($variantId, $lotId, $quantity, $reason, $date) {
            $this->assertHeld($job, $variantId, $lotId, $quantity);
            $this->stock->remove(new StockBucket($variantId, null, StockStatus::IN_PRODUCTION, Location::forContractor($job->contractor)->id, $lotId), $quantity, new StockSource($job, $date, notes: "Wastage: {$reason}"));
            $cost = Lot::query()->whereKey($lotId)->value('unit_cost');
            $this->record($job, null, 'wastage', $date, $variantId, $lotId, $quantity, $cost, $reason);
            $this->accounting->loss(Decimal::mul($quantity, $cost ?? '0'), $date, $job, "{$job->number} wastage at {$job->contractor->name}: {$reason}");
        });
    }

    /**
     * Receives output for a line. $consumed (variant id => quantity) is the
     * material used; by default the expected use for the pieces returned.
     *
     * @param  array<int, string>|null  $consumed
     */
    public function receive(ContractorJob $job, ContractorJobLine $line, string $received, string $accepted, string $rejected, CarbonInterface $date, ?string $overAction = null, ?string $rejectReason = null, ?array $consumed = null): ContractorJobTransaction
    {
        if (Decimal::compare(Decimal::add($accepted, $rejected, 4), $received) !== 0) {
            throw new DocumentException('Accepted + rejected must equal received.');
        }
        if (Decimal::compare($received, '0') <= 0) {
            throw new DocumentException('Received quantity must be above zero.');
        }
        if (Decimal::compare($rejected, '0') > 0 && trim((string) $rejectReason) === '') {
            throw new DocumentException('Give the rejection reason.');
        }

        $result = null;
        $this->run($job, $date, function (ContractorJob $job) use ($line, $received, $accepted, $rejected, $date, $overAction, $rejectReason, $consumed, &$result) {
            $line = ContractorJobLine::query()->lockForUpdate()->findOrFail($line->id);
            if ($line->contractor_job_id !== $job->id) {
                throw new DocumentException('That line belongs to another job.');
            }

            // Over-receipt beyond the agreed quantity (+ tolerance).
            $outstanding = Decimal::sub($line->quantity, $line->accepted_qty, 4);
            $outstanding = Decimal::isNegative($outstanding) ? '0' : $outstanding;
            $tolerance = Decimal::div(Decimal::mul($outstanding, $this->settings->get('tolerance.over_receipt_percent') ?? '0', 6), '100', 4);
            $extra = Decimal::sub($accepted, $outstanding, 4);
            $action = null;
            if (Decimal::compare($extra, '0') > 0) {
                if (Decimal::compare($extra, $tolerance) > 0) {
                    if (! array_key_exists((string) $overAction, self::OVER_RECEIPT_ACTIONS)) {
                        throw new DocumentException("{$extra} more than agreed was received. Choose: accept and pay, accept as free, or return the extra.");
                    }
                    $action = $overAction;
                } else {
                    $action = 'accept_pay';
                }
            }
            $extra = Decimal::compare($extra, '0') > 0 ? $extra : '0';
            $stockAccepted = $action === 'return_extra' ? Decimal::sub($accepted, $extra, 4) : $accepted;
            $paid = in_array($action, ['accept_free', 'return_extra'], true) ? Decimal::sub($accepted, $extra, 4) : $accepted;

            // Material used for the pieces (default: expected share), from what the contractor holds.
            $pieces = Decimal::add($accepted, $rejected, 4);
            $materialCost = BigDecimal::zero();
            $sourceLots = [];
            $totalQty = Decimal::sum($job->lines->pluck('quantity'), 4);
            foreach ($job->materials as $m) {
                $use = $consumed[$m->item_variant_id] ?? Decimal::div(Decimal::mul($m->expected_qty, $pieces, 6), $totalQty, 4);
                $need = Decimal::of((string) $use);
                foreach ($this->held($job)->where('item_variant_id', $m->item_variant_id) as $h) {
                    if (! $need->isPositive()) {
                        break;
                    }
                    $take = (string) BigDecimal::min($need, Decimal::of($h['quantity']))->toScale(4);
                    $this->stock->remove(new StockBucket($m->item_variant_id, null, StockStatus::IN_PRODUCTION, Location::forContractor($job->contractor)->id, $h['lot_id']), $take, new StockSource($job, $date, notes: 'Used by contractor'));
                    $cost = Lot::query()->whereKey($h['lot_id'])->value('unit_cost') ?? '0';
                    $this->record($job, $line, 'consume', $date, $m->item_variant_id, $h['lot_id'], $take, $cost);
                    $materialCost = $materialCost->plus(Decimal::of($take)->multipliedBy($cost));
                    $sourceLots[$h['lot_id']] = true;
                    $need = $need->minus(Decimal::of($take));
                }
            }

            $amount = Decimal::mul($paid, $line->rate);
            $stockPieces = Decimal::add($stockAccepted, $rejected, 4);
            $totalCost = $materialCost->plus(Decimal::of($amount));
            $unitCost = Decimal::compare($stockPieces, '0') > 0 ? (string) $totalCost->dividedBy($stockPieces, 4, RoundingMode::HalfUp) : null;

            $lot = $this->stock->createLot($line->item_variant_id, 'contractor_made', $unitCost, $date, $job, ['contractor_id' => $job->contractor_id]);
            foreach (array_keys($sourceLots) as $sourceLotId) {
                LotLink::query()->create(['lot_id' => $lot->id, 'source_lot_id' => $sourceLotId, 'document_type' => $job->getMorphClass(), 'document_id' => $job->id]);
            }
            $main = Location::mainStore();
            $source = new StockSource($job, $date, lineId: $line->id, holder: $job->contractor);
            if (Decimal::compare($stockAccepted, '0') > 0) {
                $this->stock->receive(new StockBucket($line->item_variant_id, $line->stage_id, StockStatus::AVAILABLE, $main->id, $lot->id), $stockAccepted, $source);
            }

            $journal = $this->accounting->labor($job->contractor, $amount, $date, $job, "{$job->number} {$job->contractor->name}: {$paid} × {$line->rate}");
            $tx = ContractorJobTransaction::query()->create([
                'contractor_job_id' => $job->id, 'contractor_job_line_id' => $line->id, 'type' => 'receipt', 'transaction_date' => $date->format('Y-m-d'),
                'item_variant_id' => $line->item_variant_id, 'lot_id' => $lot->id, 'quantity' => $received, 'accepted_qty' => $stockAccepted, 'rejected_qty' => $rejected,
                'paid_qty' => $paid, 'over_receipt_action' => $action, 'unit_cost' => $unitCost, 'amount' => $amount, 'journal_id' => $journal?->id,
                'reason' => $rejectReason, 'created_by' => auth()->id(),
            ]);

            if (Decimal::compare($rejected, '0') > 0) {
                $this->stock->receive(new StockBucket($line->item_variant_id, $line->stage_id, StockStatus::REJECTED, $main->id, $lot->id), $rejected, $source);
                $this->rejections->record($tx, [
                    'rejection_date' => $date, 'contractor_job_id' => $job->id, 'item_variant_id' => $line->item_variant_id, 'stage_id' => $line->stage_id,
                    'lot_id' => $lot->id, 'location_id' => $main->id, 'quantity' => $rejected, 'reason' => (string) $rejectReason,
                    'responsible_type' => $job->contractor->getMorphClass(), 'responsible_id' => $job->contractor_id,
                ]);
            }

            $line->update([
                'received_qty' => Decimal::add($line->received_qty, $received, 4),
                'accepted_qty' => Decimal::add($line->accepted_qty, $stockAccepted, 4),
                'rejected_qty' => Decimal::add($line->rejected_qty, $rejected, 4),
                'paid_qty' => Decimal::add($line->paid_qty, $paid, 4),
            ]);

            if (Decimal::compare($stockAccepted, '0') > 0) {
                $this->supply->fulfil($line, $stockAccepted, $lot->id, $line->stage_id);
            }

            $lines = $job->lines()->get();
            $job->forceFill(['status' => $lines->every(fn ($l) => Decimal::compare($l->received_qty, $l->quantity) >= 0) ? 'received' : 'partly_received'])->save();
            $result = $tx;
        });

        return $result;
    }

    /** Closes the job once its material is settled (returned, wasted or used). */
    public function close(ContractorJob $job, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }
        DB::transaction(function () use ($job, $reason) {
            $job = ContractorJob::query()->lockForUpdate()->findOrFail($job->id);
            if (! $job->isOpen()) {
                throw new DocumentException("{$job->number} is {$job->status}.");
            }
            if ($this->held($job)->isNotEmpty()) {
                throw new DocumentException('The contractor still holds material. Return it or record wastage first.');
            }
            $job->forceFill(['status' => 'closed', 'closed_at' => now(), 'close_reason' => $reason])->save();
            foreach ($job->lines as $l) {
                $this->supply->close($l);
            }
            activity('documents')->performedOn($job)->event('closed')->withProperties(['reason' => $reason])->log('Closed');
        });
    }

    /**
     * Material the contractor still holds for this job, by lot.
     *
     * @return Collection<int, array{item_variant_id: int, lot_id: int, quantity: string}>
     */
    public function held(ContractorJob $job): Collection
    {
        $signs = ['issue' => 1, 'return' => -1, 'wastage' => -1, 'consume' => -1];

        return ContractorJobTransaction::query()->where('contractor_job_id', $job->id)->whereIn('type', array_keys($signs))->get()
            ->groupBy(fn ($t) => $t->item_variant_id.'-'.$t->lot_id)
            ->map(function ($rows) use ($signs) {
                $qty = '0';
                foreach ($rows as $t) {
                    $qty = $signs[$t->type] > 0 ? Decimal::add($qty, $t->quantity, 4) : Decimal::sub($qty, $t->quantity, 4);
                }

                return ['item_variant_id' => $rows->first()->item_variant_id, 'lot_id' => (int) $rows->first()->lot_id, 'quantity' => $qty];
            })
            ->filter(fn ($r) => Decimal::compare($r['quantity'], '0') > 0)->values();
    }

    /**
     * issued = consumed + returned + wastage + still held, per material, with
     * the expected use for the pieces returned so far.
     *
     * @return Collection<int, array{variant: ItemVariant, expected: string, issued: string, consumed: string, returned: string, wastage: string, held: string}>
     */
    public function reconciliation(ContractorJob $job): Collection
    {
        $tx = ContractorJobTransaction::query()->where('contractor_job_id', $job->id)->where('type', '!=', 'receipt')->get()->groupBy('item_variant_id');
        $materials = $job->materials()->with('variant')->get()->keyBy('item_variant_id');
        $totalQty = Decimal::sum($job->lines->pluck('quantity'), 4);
        $returned = Decimal::sum($job->lines->map(fn ($l) => Decimal::add($l->accepted_qty, $l->rejected_qty, 4)), 4);

        return $materials->keys()->merge($tx->keys())->unique()->values()->map(function ($id) use ($tx, $materials, $totalQty, $returned) {
            $rows = $tx->get($id, collect());
            $sum = fn (string $t) => Decimal::sum($rows->where('type', $t)->pluck('quantity'), 4);
            $issued = $sum('issue');
            $consumed = $sum('consume');
            $back = $sum('return');
            $waste = $sum('wastage');

            return [
                'variant' => $materials[$id]->variant ?? ItemVariant::withTrashed()->findOrFail($id),
                'expected' => isset($materials[$id]) && Decimal::compare($totalQty, '0') > 0 ? Decimal::div(Decimal::mul($materials[$id]->expected_qty, $returned, 6), $totalQty, 4) : '0.0000',
                'issued' => $issued, 'consumed' => $consumed, 'returned' => $back, 'wastage' => $waste,
                'held' => Decimal::sub($issued, Decimal::add(Decimal::add($consumed, $back, 4), $waste, 4), 4),
            ];
        });
    }

    private function assertHeld(ContractorJob $job, int $variantId, int $lotId, string $quantity): void
    {
        $held = $this->held($job)->first(fn ($h) => $h['item_variant_id'] === $variantId && $h['lot_id'] === $lotId)['quantity'] ?? '0';
        if (Decimal::compare($quantity, $held) > 0) {
            throw new DocumentException("The contractor holds only {$held} of this lot for {$job->number}.");
        }
    }

    private function record(ContractorJob $job, ?ContractorJobLine $line, string $type, CarbonInterface $date, int $variantId, ?int $lotId, string $quantity, ?string $unitCost = null, ?string $reason = null): ContractorJobTransaction
    {
        return ContractorJobTransaction::query()->create([
            'contractor_job_id' => $job->id, 'contractor_job_line_id' => $line?->id, 'type' => $type, 'transaction_date' => $date->format('Y-m-d'),
            'item_variant_id' => $variantId, 'lot_id' => $lotId, 'quantity' => $quantity, 'unit_cost' => $unitCost,
            'amount' => $unitCost === null ? null : Decimal::mul($quantity, $unitCost), 'reason' => $reason, 'created_by' => auth()->id(),
        ]);
    }

    /** @param  callable(ContractorJob): void  $change */
    private function run(ContractorJob $job, CarbonInterface $date, callable $change): void
    {
        DB::transaction(function () use ($job, $date, $change) {
            $locked = ContractorJob::query()->with(['contractor', 'lines', 'materials'])->lockForUpdate()->findOrFail($job->id);
            if (! $locked->isOpen()) {
                throw new DocumentException("{$locked->number} is {$locked->status}.");
            }
            $this->periodLock->assertOpen($date, null, $locked);
            $change($locked);
        });
        $job->refresh();
    }
}
