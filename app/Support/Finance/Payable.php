<?php

namespace App\Support\Finance;

use Illuminate\Database\Eloquent\Model;

/**
 * Something a payment can be allocated to: a vendor bill, contractor job or payroll line.
 */
interface Payable
{
    /** Amount still unpaid (PKR). */
    public function payableBalance(): string;

    /** Records a paid amount; called inside the payment's transaction on a locked row. */
    public function applyPayment(string $amount): void;

    /** Whether this belongs to the given payee profile. */
    public function payableBy(Model $payee): bool;

    public function payableLabel(): string;
}
