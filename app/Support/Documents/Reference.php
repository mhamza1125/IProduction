<?php

namespace App\Support\Documents;

use Illuminate\Database\Eloquent\Model;

/**
 * How to name a record that may be any kind of source (a journal's, a stock
 * movement's or a lot's). Most sources are numbered documents, but some are not
 * (wage work, for example); reading a missing "number" would fail in strict mode.
 */
final class Reference
{
    /** The record's document number, or null when it has none. */
    public static function number(?Model $model): ?string
    {
        $number = $model?->getAttributes()['number'] ?? null;

        return $number === null ? null : (string) $number;
    }

    /** The document number, or "WageWork #12" for records without one. */
    public static function label(?Model $model): string
    {
        if ($model === null) {
            return '';
        }

        return self::number($model) ?? class_basename($model).' #'.$model->getKey();
    }
}
