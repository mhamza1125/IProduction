<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Services\Settings;
use App\Modules\People\Models\Vendor;
use App\Support\Decimal;

/**
 * Over-receipt tolerance: the item's own setting, else the vendor's, else the
 * company setting (default 0%).
 */
class Tolerance
{
    public function __construct(private readonly Settings $settings) {}

    public function percent(Item $item, Vendor $vendor): string
    {
        return (string) ($item->over_receipt_percent ?? $vendor->over_receipt_percent ?? $this->settings->get('tolerance.over_receipt_percent', '0'));
    }

    /** Most that may be accepted without an authorised decision. */
    public function allowed(string $outstanding, string $percent): string
    {
        return Decimal::mul($outstanding, Decimal::add('1', Decimal::div($percent, '100', 6), 6), Decimal::QUANTITY);
    }
}
