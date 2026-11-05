<?php

namespace App\Support\Models;

use App\Support\Documents\DocumentException;
use Closure;

/**
 * "A master record used in a transaction can only be deactivated."
 *
 * Modules that reference a master record register a check from their service
 * provider, e.g. Customer::whenUsed(fn (Customer $c) => $c->salesOrders()->exists()).
 * Deleting a record for which any check returns true throws.
 */
trait GuardsUsage
{
    /** @var list<Closure(static): bool> */
    protected static array $usageChecks = [];

    /**
     * @param  Closure(static): bool  $check
     */
    public static function whenUsed(Closure $check): void
    {
        static::$usageChecks[] = $check;
    }

    public function isInUse(): bool
    {
        foreach (static::$usageChecks as $check) {
            if ($check($this)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deletes the record, or throws if a transaction uses it.
     *
     * @throws DocumentException
     */
    public function deleteIfUnused(): void
    {
        if ($this->isInUse()) {
            throw new DocumentException('This record is used in transactions and can only be deactivated.');
        }

        $this->delete();
    }

    public static function bootGuardsUsage(): void
    {
        static::deleting(function (self $record) {
            // Force deletes and soft deletes are both blocked once used.
            if ($record->isInUse()) {
                throw new DocumentException('This record is used in transactions and can only be deactivated.');
            }
        });
    }
}
