<?php

namespace App\Support\Finance;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Something a customer receipt (or advance) can be allocated to: an invoice.
 */
interface Receivable
{
    /** Amount still owed, in the document's currency. */
    public function receivableBalance(): string;

    public function receivableCurrency(): string;

    /** Records the allocation; called inside the receipt's transaction on a locked row. */
    public function applyReceipt(Model $source, string $amount, CarbonInterface $date): void;

    public function receivableBy(Model $customer): bool;

    public function receivableLabel(): string;
}
