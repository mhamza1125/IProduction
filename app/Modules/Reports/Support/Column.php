<?php

namespace App\Modules\Reports\Support;

/**
 * A report column. Types: text, money, qty, date, number. Money columns may
 * name the row key holding their currency.
 */
final readonly class Column
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'text',
        public ?string $currencyKey = null,
        /** Row key holding a URL that makes this cell a link. */
        public ?string $linkKey = null,
        public bool $total = false,
    ) {}

    public function isNumeric(): bool
    {
        return in_array($this->type, ['money', 'qty', 'number'], true);
    }
}
