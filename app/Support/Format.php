<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Display formatting used across screens and printouts.
 * Dates are dd-mm-yyyy (specification). Blade: @date($d) @datetime($d) @money($amount, 'USD') @qty($q)
 */
final class Format
{
    public const DATE = 'd-m-Y';

    public const DATETIME = 'd-m-Y H:i';

    public static function date(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return ($value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value))->format(self::DATE);
    }

    public static function datetime(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return ($value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value))
            ->timezone(config('app.timezone'))
            ->format(self::DATETIME);
    }

    /**
     * 1234567.5 → "1,234,567.50", with an optional currency code in front.
     */
    public static function money(string|int|null $amount, ?string $currency = null, int $decimals = 2): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $formatted = self::number(Decimal::round($amount, $decimals), $decimals);

        return $currency ? $currency.' '.$formatted : $formatted;
    }

    /**
     * Quantity with trailing zeros removed: "12.5000" → "12.5", "3.0000" → "3".
     */
    public static function qty(string|int|null $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            return '';
        }

        $rounded = Decimal::round($quantity, Decimal::QUANTITY);
        $decimals = str_contains($rounded, '.') ? strlen(rtrim(substr($rounded, strpos($rounded, '.') + 1), '0')) : 0;

        return self::number($rounded, $decimals);
    }

    /**
     * Thousands separators without converting to float (keeps exact digits).
     */
    private static function number(string $value, int $decimals): string
    {
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$int, $frac] = array_pad(explode('.', $value, 2), 2, '');

        $int = ltrim($int, '0') === '' ? '0' : ltrim($int, '0');
        $int = strrev(implode(',', str_split(strrev($int), 3)));
        $frac = substr(str_pad($frac, $decimals, '0'), 0, $decimals);

        return ($negative && trim($int.$frac, '0,') !== '' ? '-' : '').$int.($decimals > 0 ? '.'.$frac : '');
    }
}
