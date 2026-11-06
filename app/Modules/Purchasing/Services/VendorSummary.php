<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Finance\Models\Payment;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\VendorBill;
use App\Support\Decimal;

/**
 * Purchasing totals for a vendor page: ordered, received, accepted, rejected,
 * returned, billed (PKR value), paid and balance.
 */
class VendorSummary
{
    /**
     * @return array<string, string>
     */
    public function for(Vendor $vendor): array
    {
        $lines = PurchaseOrderLine::query()
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendor->id)->where('status', '!=', 'cancelled')->where('status', '!=', 'draft'))
            ->selectRaw('COALESCE(SUM(amount),0) ordered, COALESCE(SUM(received_qty*rate),0) received, COALESCE(SUM(accepted_qty*rate),0) accepted,
                COALESCE(SUM(rejected_qty*rate),0) rejected, COALESCE(SUM(returned_qty*rate),0) returned')
            ->toBase()->first();

        $billed = (string) VendorBill::query()->where('vendor_id', $vendor->id)->whereNotNull('posted_at')->sum('total');
        $paid = (string) Payment::query()->where('payee_type', $vendor->getMorphClass())->where('payee_id', $vendor->id)->whereNotNull('posted_at')->sum('amount');

        return [
            'Ordered' => Decimal::round((string) $lines->ordered),
            'Received' => Decimal::round((string) $lines->received),
            'Accepted' => Decimal::round((string) $lines->accepted),
            'Rejected' => Decimal::round((string) $lines->rejected),
            'Returned' => Decimal::round((string) $lines->returned),
            'Billed' => Decimal::round($billed ?: '0'),
            'Paid' => Decimal::round($paid ?: '0'),
        ];
    }
}
