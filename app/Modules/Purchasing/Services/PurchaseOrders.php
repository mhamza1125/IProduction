<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Core\Services\UnitConverter;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Purchase order lifecycle besides approve: save drafts, mark sent, cancel,
 * close remaining quantities short, and keep the status in step with receipts.
 */
class PurchaseOrders
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly UnitConverter $units,
        private readonly DocumentWorkflow $workflow,
    ) {}

    /**
     * Creates or updates a draft. $lines: item_variant_id, stage_id, quantity, unit_id, rate, expected_date, description.
     *
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $lines
     */
    public function saveDraft(?PurchaseOrder $order, array $header, array $lines): PurchaseOrder
    {
        if ($order && ! $order->isDraft()) {
            throw new DocumentException("{$order->number} is not a draft.");
        }

        return DB::transaction(function () use ($order, $header, $lines) {
            $order ??= new PurchaseOrder([
                'number' => $this->numbers->next('purchase_order', Carbon::parse($header['order_date'])),
                'created_by' => auth()->id(),
            ]);
            $order->fill($header)->save();
            $order->lines()->delete();

            $total = '0';
            foreach ($lines as $i => $line) {
                $variant = ItemVariant::query()->with('item')->findOrFail($line['item_variant_id']);
                $factor = $this->units->factor((int) $line['unit_id'], $variant->item->stock_unit_id, $variant->item_id);

                if ($factor === null) {
                    throw new DocumentException("No conversion from the chosen unit to {$variant->item->code}'s stock unit.");
                }

                $amount = Decimal::mul($line['quantity'], $line['rate']);
                $total = Decimal::add($total, $amount);

                $order->lines()->create([
                    'item_variant_id' => $variant->id,
                    'stage_id' => $line['stage_id'] ?: null,
                    'description' => $line['description'] ?: null,
                    'quantity' => $line['quantity'],
                    'unit_id' => $line['unit_id'],
                    'unit_factor' => (string) $factor->toScale(6, RoundingMode::HalfUp),
                    'rate' => $line['rate'],
                    'amount' => $amount,
                    'expected_date' => $line['expected_date'] ?: null,
                    'sort_order' => $i,
                ]);
            }

            $order->forceFill(['total' => $total])->save();

            return $order;
        });
    }

    public function markSent(PurchaseOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'approved') {
                throw new DocumentException('Only an approved purchase order can be marked as sent.');
            }

            $locked->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
            activity('documents')->performedOn($locked)->event('sent')->log('Sent to vendor');
        });

        $order->refresh();
    }

    /**
     * Cancels an order with no receipts. Draft and approved go through the
     * workflow; sent orders are handled here.
     */
    public function cancel(PurchaseOrder $order, string $reason): void
    {
        if ($order->receipts()->whereNotNull('posted_at')->exists()) {
            throw new DocumentException("{$order->number} has posted receipts. Close the remaining quantity short instead.");
        }

        if ($order->status !== 'sent') {
            $this->workflow->cancel($order, $reason);

            return;
        }

        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }

        DB::transaction(function () use ($order, $reason) {
            PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id)
                ->forceFill(['status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            activity('documents')->performedOn($order)->event('cancelled')->withProperties(['reason' => $reason])->log('Cancelled');
        });

        $order->refresh();
    }

    /**
     * Closes everything not yet received on an order that has receipts.
     */
    public function closeShort(PurchaseOrder $order, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required to close an order short.');
        }

        DB::transaction(function () use ($order, $reason) {
            $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! $locked->isOpen()) {
                throw new DocumentException("{$locked->number} is not open.");
            }

            foreach ($locked->lines()->lockForUpdate()->get() as $line) {
                $left = $line->outstanding();
                if (Decimal::compare($left, '0') > 0) {
                    $line->update(['closed_short_qty' => Decimal::add($line->closed_short_qty, $left, Decimal::QUANTITY), 'close_reason' => $reason]);
                }
            }

            $locked->forceFill(['status' => 'closed_short'])->save();
            activity('documents')->performedOn($locked)->event('closed_short')->withProperties(['reason' => $reason])->log('Closed short');
        });

        $order->refresh();
    }

    /**
     * Sets partly_received / received from the line quantities (called after receipts and returns post).
     */
    public function refreshStatus(PurchaseOrder $order): void
    {
        if (! in_array($order->status, ['approved', 'sent', 'partly_received', 'received'], true)) {
            return;
        }

        $lines = $order->lines()->get();
        $anyAccepted = $lines->contains(fn (PurchaseOrderLine $l) => Decimal::compare($l->accepted_qty, '0') > 0);
        $allDone = $lines->every(fn (PurchaseOrderLine $l) => Decimal::isZero($l->outstanding()));

        $status = $allDone ? 'received' : ($anyAccepted ? 'partly_received' : ($order->sent_at ? 'sent' : 'approved'));
        $order->forceFill(['status' => $status])->save();
    }
}
