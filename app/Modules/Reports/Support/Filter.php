<?php

namespace App\Modules\Reports\Support;

/**
 * A report filter: date, select or text. "from"/"to" date filters drive the date range.
 */
final readonly class Filter
{
    /** @param  array<string|int, string>  $options */
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'select',
        public array $options = [],
        public ?string $default = null,
    ) {}
}
