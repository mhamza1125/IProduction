<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\Purchasing\Models\GoodsReceiptLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;

/**
 * Posts a purchase return: stock leaves the chosen lot (Available or Rejected),
 * and the vendor balance goes down: Dr Accounts Payable, Cr Inventory, at the
 * lot's cost. With "replacement required" the quantity becomes outstanding on
 * the purchase order again so a replacement can be received.
 */
class PostPurchaseReturn
{
    public function __construct(
        private readonly DocumentWorkflow $workflow,
        private readonly StockLedger $stock,
        private readonly JournalPoster $journals,
    ) {}

    public function __invoke(PurchaseReturn $return, ?string $overrideReason = null): PurchaseReturn
    {
        return $this->workflow->post($return, function (PurchaseReturn $doc) {
            $lines = $doc->lines()->get();
            if ($lines->isEmpty()) {
                throw new DocumentException('Add at least one line before posting.');
            }

            $total = '0';
            foreach ($lines as $line) {
                $grnLine = GoodsReceiptLine::query()->lockForUpdate()->findOrFail($line->goods_receipt_line_id);

                if ($grnLine->goods_receipt_id !== $doc->goods_receipt_id || $grnLine->lot_id !== $line->lot_id) {
                    throw new DocumentException('Return lines must come from the lots of this goods receipt.');
                }

                $this->stock->remove(
                    new StockBucket($line->item_variant_id, $grnLine->stage_id, $line->status, $line->location_id, $line->lot_id),
                    $line->quantity,
                    new StockSource($doc, $doc->return_date, $line->id),
                );

                $total = Decimal::add($total, $line->amount);
                $grnLine->forceFill(['returned_qty' => Decimal::add($grnLine->returned_qty, $line->quantity, Decimal::QUANTITY)])->save();

                $poLine = PurchaseOrderLine::query()->lockForUpdate()->findOrFail($grnLine->purchase_order_line_id);
                $orderQty = Decimal::div($line->quantity, $poLine->unit_factor, Decimal::QUANTITY);
                $update = ['returned_qty' => Decimal::add($poLine->returned_qty, $orderQty, Decimal::QUANTITY)];
                if ($doc->replacement_required) {
                    $update['accepted_qty'] = Decimal::sub($poLine->accepted_qty, $orderQty, Decimal::QUANTITY);
                }
                $poLine->update($update);
            }

            $doc->forceFill(['total' => $total])->save();

            $this->journals->post(
                (new JournalEntry($doc->return_date, 'PKR', "{$doc->number} purchase return", $doc))
                    ->debit('accounts_payable', $total, $doc->vendor)
                    ->credit('inventory', $total),
            );

            if ($doc->replacement_required) {
                $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($doc->receipt->purchase_order_id);
                if ($order->status === 'received') {
                    $order->forceFill(['status' => 'partly_received'])->save();
                }
            }
        }, $overrideReason);
    }
}
