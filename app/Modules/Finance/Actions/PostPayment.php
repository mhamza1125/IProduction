<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentAllocation;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Finance\Services\MoneyAccounts;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use App\Support\Finance\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Posts a payment (specification, Posting map, Vendor Payment):
 *   Dr Accounts Payable (allocated part) and Dr Advances (unallocated part), Cr the cash/bank account.
 * Contractors use Contractor Payable / Contractor Advances; employees Wages and
 * Salaries Payable / Employee Advances; an expense head is debited directly.
 * The payment cannot exceed the account balance unless overdraft is allowed.
 *
 * Allocations are passed as [['allocatable' => Payable model, 'amount' => '...'], ...].
 */
class PostPayment
{
    /** payee kind => [payable account, advance account] */
    public const ACCOUNTS = [
        'vendor' => ['accounts_payable', 'vendor_advances'],
        'contractor' => ['contractor_payable', 'contractor_advances'],
        'employee' => ['wages_payable', 'employee_advances'],
    ];

    public function __construct(
        private readonly DocumentWorkflow $workflow,
        private readonly JournalPoster $journals,
        private readonly MoneyAccounts $accounts,
    ) {}

    /**
     * @param  list<array{allocatable: Model&Payable, amount: string}>  $allocations
     */
    public function __invoke(Payment $payment, array $allocations = [], ?string $overrideReason = null): Payment
    {
        return $this->workflow->post($payment, function (Payment $doc) use ($allocations) {
            $money = $doc->moneyAccount()->firstOrFail();

            if ($money->currency_code !== 'PKR') {
                throw new DocumentException('Payments are made from PKR accounts. Convert foreign currency first.');
            }
            if (Decimal::compare($doc->amount, '0') <= 0) {
                throw new DocumentException('The amount must be above zero.');
            }

            $this->accounts->assertCanPay($money, $doc->amount);

            if ($allocations === []) {
                $allocations = $this->planned($doc);
            }

            $entry = new JournalEntry($doc->payment_date, 'PKR', "{$doc->number} payment".($doc->reference ? " ({$doc->reference})" : ''), $doc);

            if ($doc->payee_kind === 'expense') {
                if ($allocations !== []) {
                    throw new DocumentException('Expense payments are not allocated.');
                }
                $entry->debit($doc->expenseAccount()->firstOrFail(), $doc->amount);
            } else {
                $payee = $doc->payee()->firstOrFail();
                [$payableAccount, $advanceAccount] = self::ACCOUNTS[$doc->payee_kind];

                $allocated = $this->allocate($doc, $payee, $allocations);
                $advance = Decimal::sub($doc->amount, $allocated);

                if (Decimal::isNegative($advance)) {
                    throw new DocumentException('Allocations exceed the payment amount.');
                }
                if (! Decimal::isZero($allocated)) {
                    $entry->debit($payableAccount, $allocated, $payee);
                }
                if (! Decimal::isZero($advance)) {
                    $entry->debit($advanceAccount, $advance, $payee, 'Advance');
                }
            }

            $entry->credit($money, $doc->amount);
            $this->journals->post($entry);
        }, $overrideReason);
    }

    /**
     * The allocations chosen on the draft.
     *
     * @return list<array{allocatable: Model&Payable, amount: string}>
     */
    private function planned(Payment $payment): array
    {
        $out = [];

        foreach ($payment->planned_allocations ?? [] as $row) {
            $class = Relation::getMorphedModel($row['type']) ?? $row['type'];
            $model = class_exists($class) ? $class::query()->find($row['id']) : null;

            if (! $model instanceof Payable || ! $model instanceof Model) {
                throw new DocumentException('An allocation on this payment no longer exists.');
            }

            $out[] = ['allocatable' => $model, 'amount' => (string) $row['amount']];
        }

        return $out;
    }

    /**
     * @param  list<array{allocatable: Model&Payable, amount: string}>  $allocations
     * @return string total allocated
     */
    private function allocate(Payment $payment, Model $payee, array $allocations): string
    {
        $total = '0';

        foreach ($allocations as $row) {
            $amount = Decimal::round($row['amount']);
            if (Decimal::compare($amount, '0') <= 0) {
                continue;
            }

            /** @var Model&Payable $target */
            $target = $row['allocatable']->newQuery()->lockForUpdate()->findOrFail($row['allocatable']->getKey());

            if (! $target->payableBy($payee)) {
                throw new DocumentException("{$target->payableLabel()} does not belong to this payee.");
            }
            if (Decimal::compare($amount, $target->payableBalance()) > 0) {
                throw new DocumentException("{$target->payableLabel()} has only {$target->payableBalance()} left to pay.");
            }

            $target->applyPayment($amount);
            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'allocatable_type' => $target->getMorphClass(),
                'allocatable_id' => $target->getKey(),
                'amount' => $amount,
                'created_by' => auth()->id(),
            ]);
            $total = Decimal::add($total, $amount);
        }

        $payment->forceFill(['allocated_amount' => $total])->save();

        return $total;
    }
}
