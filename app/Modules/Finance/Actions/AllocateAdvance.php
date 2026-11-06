<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Finance\Payable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Applies the unallocated (advance) part of a posted payment to a bill, job
 * or payroll later: Dr Payable, Cr Advances.
 */
class AllocateAdvance
{
    public function __construct(private readonly JournalPoster $journals) {}

    public function __invoke(Payment $payment, Model&Payable $target, string $amount, CarbonInterface $date): PaymentAllocation
    {
        return DB::transaction(function () use ($payment, $target, $amount, $date) {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $amount = Decimal::round($amount);

            if ($locked->posted_at === null || ! isset(PostPayment::ACCOUNTS[$locked->payee_kind])) {
                throw new DocumentException('Only posted payments to a party can be allocated.');
            }
            if (Decimal::compare($amount, '0') <= 0 || Decimal::compare($amount, $locked->unallocated()) > 0) {
                throw new DocumentException("Only {$locked->unallocated()} of {$locked->number} is unallocated.");
            }

            $payee = $locked->payee()->firstOrFail();
            /** @var Model&Payable $target */
            $target = $target->newQuery()->lockForUpdate()->findOrFail($target->getKey());

            if (! $target->payableBy($payee)) {
                throw new DocumentException("{$target->payableLabel()} does not belong to this payee.");
            }
            if (Decimal::compare($amount, $target->payableBalance()) > 0) {
                throw new DocumentException("{$target->payableLabel()} has only {$target->payableBalance()} left to pay.");
            }

            [$payableAccount, $advanceAccount] = PostPayment::ACCOUNTS[$locked->payee_kind];
            $journal = $this->journals->post(
                (new JournalEntry($date, 'PKR', "{$locked->number} advance applied to {$target->payableLabel()}", $locked))
                    ->debit($payableAccount, $amount, $payee)
                    ->credit($advanceAccount, $amount, $payee),
            );

            $target->applyPayment($amount);
            $locked->forceFill(['allocated_amount' => Decimal::add($locked->allocated_amount, $amount)])->save();

            return PaymentAllocation::query()->create([
                'payment_id' => $locked->id,
                'allocatable_type' => $target->getMorphClass(),
                'allocatable_id' => $target->getKey(),
                'amount' => $amount,
                'journal_id' => $journal->id,
                'created_by' => auth()->id(),
            ]);
        });
    }
}
