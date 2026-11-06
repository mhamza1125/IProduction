<?php

namespace App\Modules\Purchasing\Actions;

use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Purchasing\Models\GoodsReceiptLine;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\VendorBill;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;

/**
 * Posts a vendor bill for accepted receipts. The goods value is already in
 * Accounts Payable from the goods receipt, so the bill posts only its extras:
 * tax (Dr Taxes Payable), charges (Dr Freight) and deductions (Cr Other
 * Expenses), against the vendor's Accounts Payable. Billed quantity cannot
 * exceed the payable received quantity unless the user may override.
 */
class PostVendorBill
{
    public function __construct(
        private readonly DocumentWorkflow $workflow,
        private readonly JournalPoster $journals,
    ) {}

    public function __invoke(VendorBill $bill, ?string $overrideReason = null): VendorBill
    {
        return $this->workflow->post($bill, function (VendorBill $doc) {
            $lines = $doc->lines()->get();
            if ($lines->isEmpty()) {
                throw new DocumentException('Add at least one receipt line before posting.');
            }

            foreach ($lines as $line) {
                $grnLine = GoodsReceiptLine::query()->with('receipt')->lockForUpdate()->findOrFail($line->goods_receipt_line_id);

                if ($grnLine->receipt->vendor_id !== $doc->vendor_id || $grnLine->receipt->posted_at === null) {
                    throw new DocumentException('Bills can only include posted receipts from this vendor.');
                }

                $newBilled = Decimal::add($grnLine->billed_qty, $line->quantity, Decimal::QUANTITY);
                if (Decimal::compare($newBilled, $grnLine->payable_qty) > 0 && ! auth()->user()?->can('vendor_bills.override')) {
                    throw new DocumentException("Billed quantity for {$grnLine->receipt->number} would exceed the accepted quantity.");
                }

                $grnLine->forceFill(['billed_qty' => $newBilled])->save();
                $poLine = PurchaseOrderLine::query()->lockForUpdate()->findOrFail($grnLine->purchase_order_line_id);
                $poLine->update(['billed_qty' => Decimal::add($poLine->billed_qty, $line->quantity, Decimal::QUANTITY)]);
            }

            $vendor = $doc->vendor;
            $entry = new JournalEntry($doc->bill_date, 'PKR', "{$doc->number} vendor bill {$doc->vendor_invoice_number}", $doc);
            $netExtra = Decimal::sub(Decimal::add($doc->tax_amount, $doc->charges_amount), $doc->deductions_amount);

            if (! Decimal::isZero($doc->tax_amount)) {
                $entry->debit('taxes_payable', $doc->tax_amount, memo: 'Tax on vendor bill');
            }
            if (! Decimal::isZero($doc->charges_amount)) {
                $entry->debit('freight', $doc->charges_amount, memo: 'Charges on vendor bill');
            }
            if (! Decimal::isZero($doc->deductions_amount)) {
                $entry->credit('other_expenses', $doc->deductions_amount, memo: 'Deductions on vendor bill');
            }
            if (Decimal::compare($netExtra, '0') > 0) {
                $entry->credit('accounts_payable', $netExtra, $vendor);
            } elseif (Decimal::isNegative($netExtra)) {
                $entry->debit('accounts_payable', ltrim($netExtra, '-'), $vendor);
            }

            if (count($entry->lines) >= 2) {
                $this->journals->post($entry);
            }
        }, $overrideReason);
    }
}
