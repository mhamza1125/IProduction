<?php

namespace App\Modules\Reports\Support;

use App\Support\Format;

/** Turns a report cell into display text (screen/print) or a plain value (export). */
final class ReportFormatter
{
    /** @param  array<string, mixed>  $row */
    public static function display(Column $c, array $row): string
    {
        $v = $row[$c->key] ?? null;
        if ($v === null || $v === '') {
            return '';
        }

        return match ($c->type) {
            'money' => Format::money((string) $v, $c->currencyKey ? ($row[$c->currencyKey] ?? null) : null),
            'qty' => Format::qty((string) $v),
            'date' => Format::date($v),
            default => (string) $v,
        };
    }

    /** @param  array<string, mixed>  $row */
    public static function plain(Column $c, array $row): mixed
    {
        $v = $row[$c->key] ?? null;

        return match ($c->type) {
            'date' => $v ? Format::date($v) : null,
            default => $v,
        };
    }
}
