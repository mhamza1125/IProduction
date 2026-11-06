<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\GoodsReceiptLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Services\PurchaseOrders;
use App\Modules\Purchasing\Services\Tolerance;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Brick\Math\RoundingMode;
use Closure;

/**
 * Posts a goods receipt (specification, Module 6):
 *  - received = accepted + rejected;
 *  - accepted quantity creates a Purchased lot and adds Available stock;
 *  - rejected quantity is returned (never enters stock) or kept in Rejected status;
 *  - above the over-receipt tolerance an authorised user must choose:
 *      accept_pay   – stock and pay everything accepted;
 *      accept_free  – stock everything, pay only the ordered part, so unit cost = total paid ÷ quantity;
 *      return_extra – stock and pay only the ordered part; the extra goes back;
 *  - journal: Dr Inventory, Cr Accounts Payable (vendor) for the payable value.
 */
class PostGoodsReceipt
{
    /** @var array<string, Closure(GoodsReceipt): void> */
    private static array $after = [];

    public function __construct(
        private readonly DocumentWorkflow $workflow,
        private readonly StockLedger $stock,
        private readonly JournalPoster $journals,
        private readonly Tolerance $tolerance,
        private readonly PurchaseOrders $orders,
    ) {}

    /**
     * Lets other modules act in the same transaction (e.g. reserve finished goods for orders).
     *
     * @param  Closure(GoodsReceipt): void  $hook
     */
    public static function after(string $key, Closure $hook): void
    {
        self::$after[$key] = $hook;
    }

    public function __invoke(GoodsReceipt $receipt, ?string $overrideReason = null): GoodsReceipt
    {
        return $this->workflow->post($receipt, function (GoodsReceipt $grn) {
            $order = PurchaseOrder::query()->with('vendor')->lockForUpdate()->findOrFail($grn->purchase_order_id);

            if (! $order->isOpen()) {
                throw new DocumentException("{$order->number} is not open for receiving.");
            }

            $lines = $grn->lines()->with('variant.item')->get();
            if ($lines->isEmpty()) {
                throw new DocumentException('Add at least one line before posting.');
            }

            $total = '0';
            foreach ($lines as $line) {
                $total = Decimal::add($total, $this->postLine($grn, $order, $line));
            }

            $grn->forceFill(['total_value' => $total])->save();

            if (! Decimal::isZero($total)) {
                $this->journals->post(
                    (new JournalEntry($grn->receipt_date, 'PKR', "{$grn->number} goods received ({$order->number})", $grn))
                        ->debit('inventory', $total)
                        ->credit('accounts_payable', $total, $order->vendor),
                );
            }

            $this->orders->refreshStatus($order);

            foreach (self::$after as $hook) {
                $hook($grn);
            }
        }, $overrideReason);
    }

    /**
     * @return string payable value of the line
     */
    private function postLine(GoodsReceipt $grn, PurchaseOrder $order, GoodsReceiptLine $line): string
    {
        /** @var PurchaseOrderLine $poLine */
        $poLine = PurchaseOrderLine::query()->lockForUpdate()->findOrFail($line->purchase_order_line_id);

        if ($poLine->purchase_order_id !== $order->id) {
            throw new DocumentException('A receipt line does not belong to this purchase order.');
        }

        $received = $line->received_qty;
        $accepted = $line->accepted_qty;
        $rejected = $line->rejected_qty;

        if (Decimal::compare($received, '0') <= 0 || Decimal::isNegative($accepted) || Decimal::isNegative($rejected)) {
            throw new DocumentException('Quantities must be above zero.');
        }
        if (Decimal::compare($received, Decimal::add($accepted, $rejected, Decimal::QUANTITY)) !== 0) {
            throw new DocumentException("{$line->variant->sku}: received must equal accepted + rejected.");
        }

        $outstanding = $poLine->outstanding();
        $allowed = $this->tolerance->allowed($outstanding, $this->tolerance->percent($line->variant->item, $order->vendor));
        $extra = Decimal::compare($accepted, $outstanding) > 0 ? Decimal::sub($accepted, $outstanding, Decimal::QUANTITY) : '0';
        $action = $line->over_receipt_action;

        if (Decimal::compare($accepted, $allowed) > 0) {
            if ($action === null) {
                throw new DocumentException("{$line->variant->sku}: {$accepted} accepted against {$outstanding} outstanding is above the tolerance. Choose accept and pay, accept as free, or return the extra.");
            }
            if (! auth()->user()?->can('goods_receipts.override')) {
                throw new DocumentException('Only an authorised user can decide on quantity above the tolerance.');
            }
        } else {
            $action = Decimal::isZero($extra) ? null : 'accept_pay'; // within tolerance: accept and pay
        }

        $stockAccepted = $action === 'return_extra' ? Decimal::sub($accepted, $extra, Decimal::QUANTITY) : $accepted;
        $payableAccepted = in_array($action, ['accept_free', 'return_extra'], true) ? Decimal::sub($accepted, $extra, Decimal::QUANTITY) : $accepted;
        $kept = $line->rejected_action === 'keep' ? $rejected : '0';

        $payable = Decimal::add($payableAccepted, $kept, Decimal::QUANTITY);
        $value = Decimal::mul($payable, $poLine->rate);

        $factor = $poLine->unit_factor;
        $availableStock = Decimal::mul($stockAccepted, $factor, Decimal::QUANTITY);
        $rejectedStock = Decimal::mul($kept, $factor, Decimal::QUANTITY);
        $stockTotal = Decimal::add($availableStock, $rejectedStock, Decimal::QUANTITY);
        $unitCost = Decimal::isZero($stockTotal) ? null : (string) Decimal::of($value)->dividedBy(Decimal::of($stockTotal), 4, RoundingMode::HalfUp);

        $lotId = null;
        if (! Decimal::isZero($stockTotal)) {
            $lot = $this->stock->createLot($line->item_variant_id, 'purchased', $unitCost, $grn->receipt_date, $grn, ['vendor_id' => $order->vendor_id, 'notes' => $order->number]);
            $lotId = $lot->id;
            $source = new StockSource($grn, $grn->receipt_date, $line->id);
            $bucket = new StockBucket($line->item_variant_id, $line->stage_id, StockStatus::AVAILABLE, $line->location_id, $lot->id);

            if (! Decimal::isZero($availableStock)) {
                $this->stock->receive($bucket, $availableStock, $source);
            }
            if (! Decimal::isZero($rejectedStock)) {
                $this->stock->receive($bucket->with(status: StockStatus::REJECTED), $rejectedStock, $source);
            }
        }

        $line->forceFill([
            'over_receipt_action' => $action,
            'extra_qty' => $extra,
            'stock_qty' => $availableStock,
            'payable_qty' => $payable,
            'rate' => $poLine->rate,
            'unit_cost' => $unitCost,
            'value' => $value,
            'lot_id' => $lotId,
        ])->save();

        $poLine->update([
            'received_qty' => Decimal::add($poLine->received_qty, $received, Decimal::QUANTITY),
            'accepted_qty' => Decimal::add($poLine->accepted_qty, $stockAccepted, Decimal::QUANTITY),
            'rejected_qty' => Decimal::add($poLine->rejected_qty, $rejected, Decimal::QUANTITY),
        ]);

        return $value;
    }
}
