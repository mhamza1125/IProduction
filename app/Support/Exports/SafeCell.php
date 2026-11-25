<?php

namespace App\Support\Exports;

/**
 * Neutralises spreadsheet formulas in exported text (CSV/formula injection):
 * a customer name such as "=HYPERLINK(...)" typed into the system would
 * otherwise run as a formula when someone opens the export in Excel.
 *
 * Text starting with = + - @ tab or carriage return gets a leading apostrophe,
 * so Excel shows it as text. Numbers (including negative amounts such as
 * "-960.00") are left alone so they stay numeric.
 */
final class SafeCell
{
    public static function value(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::value(...), $row);
    }

    /**
     * @param  array<int|string, array<int|string, mixed>>  $rows
     * @return array<int|string, array<int|string, mixed>>
     */
    public static function rows(array $rows): array
    {
        return array_map(self::row(...), $rows);
    }
}
