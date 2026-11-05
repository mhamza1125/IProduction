<?php

namespace App\Modules\Core\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gapless document numbers such as "SO/2026-27/00001".
 *
 * Call it inside the same DB transaction that saves the document:
 *
 *   DB::transaction(function () {
 *       $order->number = app(DocumentNumber::class)->next('sales_order', 'SO', $order->date);
 *       $order->save();
 *   });
 *
 * The sequence row is locked until the transaction commits, so two users can
 * never get the same number, and a rolled-back save does not use up a number.
 */
class DocumentNumber
{
    public function __construct(private readonly FiscalYear $fiscalYear) {}

    /**
     * @param  string  $key  Sequence identifier, e.g. "sales_order".
     * @param  string  $prefix  Printed prefix, e.g. "SO".
     * @param  bool  $perFiscalYear  Restart numbering every fiscal year.
     */
    public function next(
        string $key,
        string $prefix,
        ?CarbonInterface $date = null,
        int $padding = 5,
        bool $perFiscalYear = true,
    ): string {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('DocumentNumber::next() must be called inside a database transaction.');
        }

        $period = $perFiscalYear ? $this->fiscalYear->label($date ?? Date::now()) : '';

        // Make sure the row exists, then lock it. insertOrIgnore avoids a race
        // when two transactions create the first number of a period together.
        DB::table('document_sequences')->insertOrIgnore([
            'key' => $key,
            'period' => $period,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('document_sequences')
            ->where('key', $key)
            ->where('period', $period)
            ->lockForUpdate()
            ->first(['id', 'next_number']);

        if ($sequence === null) {
            throw new LogicException("Could not lock document sequence [{$key}].");
        }

        $number = (int) $sequence->next_number;

        DB::table('document_sequences')
            ->where('id', $sequence->id)
            ->update(['next_number' => $number + 1, 'updated_at' => now()]);

        $parts = array_filter([$prefix, $period, str_pad((string) $number, $padding, '0', STR_PAD_LEFT)], fn ($p) => $p !== '');

        return implode('/', $parts);
    }
}
