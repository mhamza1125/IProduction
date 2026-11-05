<?php

namespace App\Modules\Inventory\Models;

/**
 * Stock statuses from the specification. Only Available stock can be used.
 */
final class StockStatus
{
    public const AVAILABLE = 'available';

    public const IN_PRODUCTION = 'in_production';

    public const REJECTED = 'rejected';

    public const RETURNED = 'returned';

    public const LABELS = [
        self::AVAILABLE => 'Available',
        self::IN_PRODUCTION => 'In Production',
        self::REJECTED => 'Rejected',
        self::RETURNED => 'Returned',
    ];
}
