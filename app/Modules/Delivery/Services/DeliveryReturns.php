<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Delivery\Models\DeliveryAllocation;
use App\Modules\Delivery\Models\DeliveryLine;
use App\Modules\Delivery\Models\DeliveryReturn;
use App\Modules\Delivery\Models\InvoiceLine;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\Production\Models\Rejection;
use App\Modules\Production\Services\Rejections;
use App\Modules\Sales\Services\OrderProgress;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Delivery returns (specification, Module 9). Posting brings the goods back
 * in Returned status (same lot, so it keeps its original source),
 * Dr Inventory, Cr Cost of Goods Sold at the dispatched cost, and reduces the
 * delivered quantity (reopening the order line when a replacement is needed).
 * Inspection then restocks pieces to Available at a stage, or sends them to
 * Rejections for rework, scrap or sale.
 */
class DeliveryReturns
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly StockLedger $stock,
        private readonly JournalPoster $journals,
        private readonly OrderProgress $progress,
        private readonly Rejections $rejections,
    ) {}

    /** @param  array<string, mixed>  $data  delivery_line_id, lot_id, quantity, reason, return_date, replacement_required, notes */
    public function save(?DeliveryReturn $return, array $data): DeliveryReturn
    {
        if ($return && ! $return->isDraft()) {
            throw new DocumentException("{$return->number} is {$return->status} and cannot be edited.");
        }
        $line = DeliveryLine::query()->with(['delivery', 'allocations'])->findOrFail($data['delivery_line_id']);
        if ($line->delivery->status !== 'dispatched') {
            throw new DocumentException('Only dispatched goods can be returned.');
        }
        if (trim((string) ($data['reason'] ?? '')) === '') {
            throw new DocumentException('A reason is required.');
        }
        $lotId = (int) ($data['lot_id'] ?? 0) ?: $line->allocations->first()?->lot_id;
        $sent = Decimal::sum($line->allocations->where('lot_id', $lotId)->pluck('quantity'), 4);
        $back = Decimal::sum(DeliveryReturn::query()->where('delivery_line_id', $line->id)->where('lot_id', $lotId)->where('status', '!=', 'cancelled')
            ->when($return, fn ($q) => $q->whereKeyNot($return->id))->pluck('quantity'), 4);
        $left = Decimal::sub($sent, $back, 4);
        if (Decimal::compare((string) $data['quantity'], '0') <= 0 || Decimal::compare((string) $data['quantity'], $left) > 0) {
            throw new DocumentException("Return between 0 and the {$left} delivered from this lot that has not come back.");
        }

        return DB::transaction(function () use ($return, $data, $line, $lotId) {
            $return ??= new DeliveryReturn(['number' => $this->numbers->next('delivery_return', Carbon::parse($data['return_date'])), 'created_by' => auth()->id()]);
            $return->fill([
                'customer_id' => $line->delivery->customer_id, 'delivery_line_id' => $line->id, 'lot_id' => $lotId,
                'invoice_line_id' => InvoiceLine::query()->where('delivery_line_id', $line->id)->whereHas('invoice', fn ($q) => $q->whereNotNull('posted_at'))->value('id'),
                'quantity' => $data['quantity'], 'reason' => $data['reason'], 'return_date' => $data['return_date'],
                'replacement_required' => (bool) ($data['replacement_required'] ?? false), 'notes' => $data['notes'] ?? null ?: null,
            ])->save();

            return $return;
        });
    }

    public function post(DeliveryReturn $return, ?string $overrideReason = null): DeliveryReturn
    {
        return $this->workflow->post($return, function (DeliveryReturn $r) {
            $line = DeliveryLine::query()->with(['orderLine', 'delivery'])->lockForUpdate()->findOrFail($r->delivery_line_id);
            $allocation = DeliveryAllocation::query()->where('delivery_line_id', $line->id)->where('lot_id', $r->lot_id)->firstOrFail();
            $unit = $allocation->unit_cost ?? '0';

            $this->stock->receive(new StockBucket($line->item_variant_id, $allocation->stage_id, StockStatus::RETURNED, Location::mainStore()->id, $r->lot_id),
                $r->quantity, new StockSource($r, $r->return_date, salesOrderLineId: $line->sales_order_line_id, notes: $r->reason), $unit);

            $amount = Decimal::mul($r->quantity, $unit);
            if (Decimal::compare($amount, '0') > 0) {
                $this->journals->post((new JournalEntry($r->return_date, 'PKR', "{$r->number} returned by {$line->delivery->customer->name}", $r))
                    ->debit('inventory', $amount)->credit('cogs', $amount));
            }
            $r->forceFill(['unit_cost' => $unit])->save();
            $line->update(['returned_qty' => Decimal::add($line->returned_qty, $r->quantity, 4)]);
            $this->progress->returned($line->orderLine, $r->quantity, $r->replacement_required);
        }, $overrideReason);
    }

    /** Inspection: back to Available stock at a stage (NULL = finished). */
    public function restock(DeliveryReturn $return, string $quantity, ?int $stageId, CarbonInterface $date): void
    {
        DB::transaction(function () use ($return, $quantity, $stageId, $date) {
            $r = $this->lockForInspection($return, $quantity);
            $from = $this->returnedBucket($r);
            $this->stock->transfer($from, $from->with(StockStatus::AVAILABLE, null, $stageId, $stageId === null), $quantity, new StockSource($r, $date, notes: 'Restocked after inspection'));
            $r->forceFill(['restocked_qty' => Decimal::add($r->restocked_qty, $quantity, 4)])->save();
        });
    }

    /** Inspection: to Rejected, where a rejection decision reworks, scraps or sells it. */
    public function reject(DeliveryReturn $return, string $quantity, string $reason, CarbonInterface $date): Rejection
    {
        if (trim($reason) === '') {
            throw new DocumentException('Give the reason.');
        }

        return DB::transaction(function () use ($return, $quantity, $reason, $date) {
            $r = $this->lockForInspection($return, $quantity);
            $from = $this->returnedBucket($r);
            $this->stock->transfer($from, $from->with(StockStatus::REJECTED), $quantity, new StockSource($r, $date, notes: $reason));
            $r->forceFill(['rejected_qty' => Decimal::add($r->rejected_qty, $quantity, 4)])->save();

            return $this->rejections->record($r, [
                'rejection_date' => $date, 'item_variant_id' => $from->variantId, 'stage_id' => $from->stageId, 'lot_id' => $r->lot_id,
                'location_id' => $from->locationId, 'quantity' => $quantity, 'reason' => "Customer return {$r->number}: {$reason}", 'found_by' => auth()->user()?->name,
            ]);
        });
    }

    private function lockForInspection(DeliveryReturn $return, string $quantity): DeliveryReturn
    {
        $r = DeliveryReturn::query()->lockForUpdate()->findOrFail($return->id);
        if (! $r->isPosted()) {
            throw new DocumentException('Post the return before inspecting it.');
        }
        if (Decimal::compare($quantity, '0') <= 0 || Decimal::compare($quantity, $r->uninspected()) > 0) {
            throw new DocumentException("Only {$r->uninspected()} returned pieces are waiting for inspection.");
        }

        return $r;
    }

    private function returnedBucket(DeliveryReturn $r): StockBucket
    {
        $line = DeliveryLine::query()->findOrFail($r->delivery_line_id);
        $stage = DeliveryAllocation::query()->where('delivery_line_id', $line->id)->where('lot_id', $r->lot_id)->value('stage_id');

        return new StockBucket($line->item_variant_id, $stage, StockStatus::RETURNED, Location::mainStore()->id, $r->lot_id);
    }
}
