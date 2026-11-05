<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\UnitConversion;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Converts quantities between units. An item-specific conversion wins over a
 * general one; a conversion can be used in either direction.
 */
class UnitConverter
{
    public function convert(string|int $quantity, int $fromUnitId, int $toUnitId, ?int $itemId = null, int $scale = Decimal::QUANTITY): string
    {
        if ($fromUnitId === $toUnitId) {
            return Decimal::round((string) $quantity, $scale);
        }

        $factor = $this->factor($fromUnitId, $toUnitId, $itemId);

        if ($factor === null) {
            throw new DocumentException('No unit conversion is set up between these units'.($itemId ? ' for this item' : '').'.');
        }

        return (string) Decimal::of((string) $quantity)->multipliedBy($factor)->toScale($scale, RoundingMode::HalfUp);
    }

    /**
     * How many to-units one from-unit equals, or NULL if unknown.
     */
    public function factor(int $fromUnitId, int $toUnitId, ?int $itemId = null): ?BigDecimal
    {
        foreach ($itemId !== null ? [$itemId, null] : [null] as $scope) {
            $direct = UnitConversion::query()->where('item_id', $scope)->where('from_unit_id', $fromUnitId)->where('to_unit_id', $toUnitId)->value('factor');
            if ($direct !== null) {
                return Decimal::of((string) $direct);
            }

            $inverse = UnitConversion::query()->where('item_id', $scope)->where('from_unit_id', $toUnitId)->where('to_unit_id', $fromUnitId)->value('factor');
            if ($inverse !== null) {
                return BigDecimal::one()->dividedBy(Decimal::of((string) $inverse), 10, RoundingMode::HalfUp);
            }
        }

        return null;
    }
}
