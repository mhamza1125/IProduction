<?php

namespace App\Modules\Production\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\LotLink;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcTransaction;
use App\Modules\Production\Models\Rejection;
use App\Modules\Production\Models\RejectionDecision;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Rejections (specification, Module 8). Rejected pieces stay in Rejected
 * status at their stage until decisions are posted; one rejection can be split
 * across decisions, each approved and posted:
 *   rework  – back to Available at a stage of an open PTC (extra work adds cost)
 *   scrap   – stock out, Dr Production Loss, Cr Inventory
 *   sell    – earmarked for a sales order marked for rejected stock
 *   recover – charge the responsible employee or contractor (no stock effect)
 */
class Rejections
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly StockLedger $stock,
        private readonly DocumentWorkflow $workflow,
        private readonly ProductionAccounting $accounting,
        private readonly PtcCosting $costing,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function record(?Model $source, array $data): Rejection
    {
        $date = $data['rejection_date'] instanceof CarbonInterface ? $data['rejection_date'] : Carbon::parse($data['rejection_date']);

        return Rejection::query()->create([
            'number' => $this->numbers->next('rejection', $date),
            'rejection_date' => $date->format('Y-m-d'),
            'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(),
            'created_by' => auth()->id(),
        ] + array_merge($data, ['rejection_date' => $date->format('Y-m-d')]));
    }

    /** Usable stock found faulty in a store: Available → Rejected. */
    public function rejectStock(int $variantId, ?int $stageId, int $lotId, int $locationId, string $quantity, string $reason, CarbonInterface $date, ?Model $responsible = null, ?string $foundBy = null): Rejection
    {
        if (trim($reason) === '') {
            throw new DocumentException('Give the rejection reason.');
        }

        return DB::transaction(function () use ($variantId, $stageId, $lotId, $locationId, $quantity, $reason, $date, $responsible, $foundBy) {
            $this->stock->lockVariant($variantId);
            $free = $this->stock->freeQuantity($variantId, $stageId, $lotId);
            if (Decimal::compare($quantity, $free) > 0) {
                throw new DocumentException("Only {$free} of this lot is free (reserved stock must be released first).");
            }
            $rejection = $this->record(null, [
                'rejection_date' => $date, 'item_variant_id' => $variantId, 'stage_id' => $stageId, 'lot_id' => $lotId, 'location_id' => $locationId,
                'quantity' => $quantity, 'reason' => $reason, 'found_by' => $foundBy,
                'responsible_type' => $responsible?->getMorphClass(), 'responsible_id' => $responsible?->getKey(),
            ]);
            $from = new StockBucket($variantId, $stageId, StockStatus::AVAILABLE, $locationId, $lotId);
            $this->stock->transfer($from, $from->with(StockStatus::REJECTED), $quantity, new StockSource($rejection, $date, notes: $reason));

            return $rejection;
        });
    }

    /** @param  array<string, mixed>  $data  decision, quantity, target_ptc_id, target_stage_id, amount, reason, decision_date */
    public function decide(Rejection $rejection, array $data): RejectionDecision
    {
        $decision = $data['decision'];
        if (! array_key_exists($decision, RejectionDecision::DECISIONS)) {
            throw new DocumentException('Unknown decision.');
        }
        if (trim((string) ($data['reason'] ?? '')) === '') {
            throw new DocumentException('A reason is required.');
        }
        $qty = ($data['quantity'] ?? '') === '' ? null : (string) $data['quantity'];

        if ($decision === 'recover') {
            if (Decimal::compare((string) ($data['amount'] ?? '0'), '0') <= 0) {
                throw new DocumentException('Enter the amount to charge.');
            }
            if ($rejection->responsible === null) {
                throw new DocumentException('No responsible employee or contractor is recorded for this rejection.');
            }
        } else {
            if ($qty === null || Decimal::compare($qty, '0') <= 0) {
                throw new DocumentException('Enter the quantity.');
            }
            if (Decimal::compare($qty, $this->open($rejection)) > 0) {
                throw new DocumentException('Only '.$this->open($rejection).' pieces are still undecided.');
            }
        }
        if ($decision === 'rework') {
            $ptc = Ptc::query()->find($data['target_ptc_id'] ?? null);
            if (! $ptc || ! $ptc->isActive() || $ptc->item_variant_id !== $rejection->item_variant_id) {
                throw new DocumentException('Choose an open PTC for the same variant to rework in.');
            }
            if (! $ptc->stages()->where('stage_id', $data['target_stage_id'] ?? null)->exists()) {
                throw new DocumentException('Choose a stage of that PTC.');
            }
        }

        return DB::transaction(fn () => RejectionDecision::query()->create([
            'number' => $this->numbers->next('rejection_decision', Carbon::parse($data['decision_date'] ?? now())),
            'rejection_id' => $rejection->id, 'decision_date' => $data['decision_date'] ?? now()->toDateString(), 'decision' => $decision,
            'quantity' => $qty, 'target_ptc_id' => $decision === 'rework' ? $data['target_ptc_id'] : null,
            'target_stage_id' => $decision === 'rework' ? $data['target_stage_id'] : null,
            'amount' => $decision === 'recover' ? $data['amount'] : null, 'reason' => $data['reason'], 'created_by' => auth()->id(),
        ]));
    }

    /** Quantity not yet covered by a live (non-cancelled) stock decision. */
    public function open(Rejection $rejection): string
    {
        $planned = Decimal::sum($rejection->decisions()->where('decision', '!=', 'recover')->where('status', '!=', 'cancelled')->pluck('quantity'), 4);

        return Decimal::sub($rejection->quantity, $planned, 4);
    }

    public function post(RejectionDecision $decision): RejectionDecision
    {
        return $this->workflow->post($decision, function (RejectionDecision $d) {
            $rejection = Rejection::query()->with(['lot', 'responsible'])->lockForUpdate()->findOrFail($d->rejection_id);
            $date = $d->decision_date;
            $source = new StockSource($d, $date, notes: RejectionDecision::DECISIONS[$d->decision].": {$d->reason}");
            $from = new StockBucket($rejection->item_variant_id, $rejection->stage_id, StockStatus::REJECTED, $rejection->location_id, $rejection->lot_id);
            $unitCost = $rejection->lot->unit_cost ?? '0';

            switch ($d->decision) {
                case 'rework':
                    $ptc = Ptc::query()->lockForUpdate()->findOrFail($d->target_ptc_id);
                    if (! $ptc->isActive()) {
                        throw new DocumentException("{$ptc->number} is not active.");
                    }
                    $floor = Location::productionFloor();
                    if ($ptc->output_lot_id === $rejection->lot_id) {
                        $this->stock->transfer($from, new StockBucket($rejection->item_variant_id, $d->target_stage_id, StockStatus::AVAILABLE, $floor->id, $rejection->lot_id), (string) $d->quantity, $source);
                    } else {
                        $this->stock->remove($from, (string) $d->quantity, $source);
                        $this->stock->receive(new StockBucket($rejection->item_variant_id, $d->target_stage_id, StockStatus::AVAILABLE, $floor->id, (int) $ptc->output_lot_id), (string) $d->quantity, $source);
                        LotLink::query()->create(['lot_id' => $ptc->output_lot_id, 'source_lot_id' => $rejection->lot_id, 'quantity' => $d->quantity, 'document_type' => $d->getMorphClass(), 'document_id' => $d->id]);
                        $this->costing->add($ptc, 'rework', Decimal::mul((string) $d->quantity, $unitCost), $d, "Rework from {$rejection->number}");
                    }
                    $stage = $ptc->stages()->where('stage_id', $d->target_stage_id)->firstOrFail();
                    PtcTransaction::query()->create(['ptc_id' => $ptc->id, 'ptc_stage_id' => $stage->id, 'type' => 'rework_in', 'transaction_date' => $date,
                        'item_variant_id' => $ptc->item_variant_id, 'lot_id' => $ptc->output_lot_id, 'location_id' => $floor->id, 'quantity' => $d->quantity,
                        'reason' => "{$rejection->number}: {$d->reason}", 'created_by' => auth()->id()]);
                    break;

                case 'scrap':
                    $this->stock->remove($from, (string) $d->quantity, $source);
                    $journal = $this->accounting->loss(Decimal::mul((string) $d->quantity, $unitCost), $date, $d, "{$d->number} scrap {$rejection->number}");
                    $d->forceFill(['journal_id' => $journal?->id])->save();
                    break;

                case 'sell':
                    // Stays Rejected; delivered later through a sales order marked for rejected stock.
                    break;

                case 'recover':
                    $party = $rejection->responsible;
                    if (! $party instanceof Employee && ! $party instanceof Contractor) {
                        throw new DocumentException('Charges can only be recovered from an employee or contractor.');
                    }
                    $journal = $this->accounting->recoverCharge($party, (string) $d->amount, $date, $d, "{$d->number} damage charge {$rejection->number}");
                    $d->forceFill(['journal_id' => $journal->id])->save();
                    break;
            }

            if ($d->decision !== 'recover') {
                $decided = Decimal::add($rejection->decided_qty, (string) $d->quantity, 4);
                $rejection->update(['decided_qty' => $decided, 'status' => Decimal::compare($decided, $rejection->quantity) >= 0 ? 'decided' : 'open']);
            }
        });
    }
}
