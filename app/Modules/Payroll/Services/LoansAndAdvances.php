<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Finance\Services\MoneyAccounts;
use App\Modules\Payroll\Models\EmployeeLoan;
use App\Modules\Payroll\Models\LoanInstallmentChange;
use App\Modules\Payroll\Models\PayrollLine;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\People\Models\Employee;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Employee loans and salary advances (specification, Module 11).
 *   Loan issued:     Dr Employee Loans, Cr cash/bank
 *   Salary advance:  Dr Employee Advances, Cr cash/bank
 * Payroll recovers them (installments and advances deducted).
 */
class LoansAndAdvances
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly JournalPoster $journals,
        private readonly MoneyAccounts $accounts,
    ) {}

    /** @param  array<string, mixed>  $data  employee_id, loan_date, amount, money_account_id, installment, start_month, notes */
    public function saveLoan(?EmployeeLoan $loan, array $data): EmployeeLoan
    {
        if ($loan && ! $loan->isDraft()) {
            throw new DocumentException("{$loan->number} is {$loan->status} and cannot be edited.");
        }
        $this->assertPkrAccount((int) $data['money_account_id']);
        $amount = Decimal::round((string) $data['amount']);
        $installment = Decimal::round((string) $data['installment']);
        if (Decimal::compare($amount, '0') <= 0 || Decimal::compare($installment, '0') <= 0) {
            throw new DocumentException('The amount and installment must be above zero.');
        }
        if (Decimal::compare($installment, $amount) > 0) {
            throw new DocumentException('The installment cannot be more than the loan.');
        }
        $this->assertActiveEmployee((int) $data['employee_id']);

        return DB::transaction(function () use ($loan, $data, $amount, $installment) {
            $loan ??= new EmployeeLoan(['number' => $this->numbers->next('employee_loan', Carbon::parse($data['loan_date'])), 'created_by' => auth()->id()]);
            $loan->fill(['employee_id' => $data['employee_id'], 'loan_date' => $data['loan_date'], 'amount' => $amount, 'money_account_id' => $data['money_account_id'],
                'installment' => $installment, 'start_month' => Carbon::parse($data['start_month'])->startOfMonth()->format('Y-m-d'), 'notes' => $data['notes'] ?? null ?: null])->save();

            return $loan;
        });
    }

    public function postLoan(EmployeeLoan $loan, ?string $overrideReason = null): EmployeeLoan
    {
        return $this->workflow->post($loan, function (EmployeeLoan $l) {
            $money = $l->moneyAccount()->firstOrFail();
            $this->accounts->assertCanPay($money, $l->amount);
            $this->journals->post((new JournalEntry($l->loan_date, 'PKR', "{$l->number} loan to {$l->employee->name}", $l))
                ->debit('employee_loans', $l->amount, $l->employee)->credit($money, $l->amount));
        }, $overrideReason);
    }

    /** Reduce, skip (0) or increase one month's installment, with a reason. */
    public function changeInstallment(EmployeeLoan $loan, CarbonInterface $month, string $amount, string $reason): LoanInstallmentChange
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }
        if (! $loan->isPosted()) {
            throw new DocumentException('Only posted loans have installments.');
        }
        if (Decimal::isNegative($amount)) {
            throw new DocumentException('The installment cannot be negative.');
        }
        $month = $month->copy()->startOfMonth();
        if (PayrollLine::query()->where('employee_id', $loan->employee_id)->whereDate('month', $month)->whereNotNull('posted_key')->exists()) {
            throw new DocumentException('Payroll for '.$month->format('M Y').' is already posted.');
        }
        $change = LoanInstallmentChange::query()->updateOrCreate(['employee_loan_id' => $loan->id, 'month' => $month->format('Y-m-d')],
            ['amount' => Decimal::round($amount), 'reason' => $reason, 'created_by' => auth()->id()]);
        activity('documents')->performedOn($loan)->event('installment_changed')->withProperties(['month' => $month->format('Y-m'), 'amount' => $amount, 'reason' => $reason])->log('Installment changed');

        return $change;
    }

    /** @param  array<string, mixed>  $data  employee_id, advance_date, amount, salary_month, money_account_id, notes */
    public function saveAdvance(?SalaryAdvance $advance, array $data): SalaryAdvance
    {
        if ($advance && ! $advance->isDraft()) {
            throw new DocumentException("{$advance->number} is {$advance->status} and cannot be edited.");
        }
        $this->assertPkrAccount((int) $data['money_account_id']);
        $amount = Decimal::round((string) $data['amount']);
        if (Decimal::compare($amount, '0') <= 0) {
            throw new DocumentException('The amount must be above zero.');
        }
        $this->assertActiveEmployee((int) $data['employee_id']);

        return DB::transaction(function () use ($advance, $data, $amount) {
            $advance ??= new SalaryAdvance(['number' => $this->numbers->next('salary_advance', Carbon::parse($data['advance_date'])), 'created_by' => auth()->id()]);
            $advance->fill(['employee_id' => $data['employee_id'], 'advance_date' => $data['advance_date'], 'amount' => $amount, 'money_account_id' => $data['money_account_id'],
                'salary_month' => Carbon::parse($data['salary_month'])->startOfMonth()->format('Y-m-d'), 'notes' => $data['notes'] ?? null ?: null])->save();

            return $advance;
        });
    }

    public function postAdvance(SalaryAdvance $advance, ?string $overrideReason = null): SalaryAdvance
    {
        return $this->workflow->post($advance, function (SalaryAdvance $a) {
            $money = $a->moneyAccount()->firstOrFail();
            $this->accounts->assertCanPay($money, $a->amount);
            $this->journals->post((new JournalEntry($a->advance_date, 'PKR', "{$a->number} salary advance to {$a->employee->name} for ".$a->salary_month->format('M Y'), $a))
                ->debit('employee_advances', $a->amount, $a->employee)->credit($money, $a->amount));
        }, $overrideReason);
    }

    private function assertPkrAccount(int $id): void
    {
        $account = MoneyAccount::query()->findOrFail($id);
        if ($account->currency_code !== 'PKR' || ! $account->is_active) {
            throw new DocumentException('Pay from an active PKR cash or bank account.');
        }
    }

    private function assertActiveEmployee(int $id): void
    {
        if (! Employee::query()->whereKey($id)->value('is_active')) {
            throw new DocumentException('Choose an active employee.');
        }
    }
}
