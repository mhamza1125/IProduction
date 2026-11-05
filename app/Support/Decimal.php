<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Exact decimal arithmetic for money and quantities.
 *
 * Never use PHP floats for amounts: 0.1 + 0.2 !== 0.3. Store amounts in
 * DECIMAL columns (Eloquent returns them as strings) and do the maths here.
 * Every method returns a plain numeric string, ready to save to the database.
 *
 * Rounding is HALF_UP (0.005 -> 0.01), the usual commercial rule.
 */
final class Decimal
{
    /** Scale used for money columns (DECIMAL(18,2)). */
    public const MONEY = 2;

    /** Scale used for quantity and rate columns (DECIMAL(18,4)). */
    public const QUANTITY = 4;

    /** Scale used for unit costs and exchange rates (DECIMAL(20,6)). */
    public const RATE = 6;

    public static function of(BigDecimal|int|string|null $value): BigDecimal
    {
        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        if (is_string($value) && ! is_numeric($value)) {
            throw new InvalidArgumentException("Not a number: [{$value}].");
        }

        return BigDecimal::of($value);
    }

    public static function add(BigDecimal|int|string|null $a, BigDecimal|int|string|null $b, int $scale = self::MONEY): string
    {
        return (string) self::of($a)->plus(self::of($b))->toScale($scale, RoundingMode::HalfUp);
    }

    public static function sub(BigDecimal|int|string|null $a, BigDecimal|int|string|null $b, int $scale = self::MONEY): string
    {
        return (string) self::of($a)->minus(self::of($b))->toScale($scale, RoundingMode::HalfUp);
    }

    public static function mul(BigDecimal|int|string|null $a, BigDecimal|int|string|null $b, int $scale = self::MONEY): string
    {
        return (string) self::of($a)->multipliedBy(self::of($b))->toScale($scale, RoundingMode::HalfUp);
    }

    public static function div(BigDecimal|int|string|null $a, BigDecimal|int|string|null $b, int $scale = self::MONEY): string
    {
        $divisor = self::of($b);

        if ($divisor->isZero()) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return (string) self::of($a)->dividedBy($divisor, $scale, RoundingMode::HalfUp);
    }

    /**
     * @param  iterable<BigDecimal|int|string|null>  $values
     */
    public static function sum(iterable $values, int $scale = self::MONEY): string
    {
        $total = BigDecimal::zero();

        foreach ($values as $value) {
            $total = $total->plus(self::of($value));
        }

        return (string) $total->toScale($scale, RoundingMode::HalfUp);
    }

    public static function round(BigDecimal|int|string|null $value, int $scale = self::MONEY): string
    {
        return (string) self::of($value)->toScale($scale, RoundingMode::HalfUp);
    }

    /**
     * Returns -1, 0 or 1.
     */
    public static function compare(BigDecimal|int|string|null $a, BigDecimal|int|string|null $b): int
    {
        return self::of($a)->compareTo(self::of($b));
    }

    public static function isZero(BigDecimal|int|string|null $value): bool
    {
        return self::of($value)->isZero();
    }

    public static function isNegative(BigDecimal|int|string|null $value): bool
    {
        return self::of($value)->isNegative();
    }
}
