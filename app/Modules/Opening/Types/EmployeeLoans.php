<?php

namespace App\Modules\Opening\Types;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\OpeningContext;
use App\Modules\Payroll\Models\EmployeeLoan;
use App\Modules\Payroll\Services\LoansAndAdvances;
use App\Modules\People\Models\Employee;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/** Loans still being recovered: Dr Employee Loans, Cr Opening Balance. Payroll continues the installments. */
class EmployeeLoans extends BaseType
{
    public function key(): string
    {
        return 'employee_loans';
    }

    public function label(): string
    {
        return 'Employee loans';
    }

    public function columns(): array
    {
        return ['employee_code' => 'Employee code', 'remaining' => 'Remaining balance (PKR)', 'installment' => 'Monthly installment', 'start_month' => 'First month payroll deducts (yyyy-mm)'];
    }

    public function example(): array
    {
        return ['employee_code' => 'EMP-0001', 'remaining' => '60000', 'installment' => '10000', 'start_month' => '2026-12'];
    }

    public function validate(array $row, OpeningContext $context): array
    {
        $e = [];
        if (! $this->party(Employee::class, $row['employee_code'])) {
            $e[] = "Unknown employee \"{$row['employee_code']}\".";
        }
        if (! $this->positive($row['remaining']) || ! $this->positive($row['installment'])) {
            $e[] = 'Remaining balance and installment must be above zero.';
        } elseif (Decimal::compare((string) $row['installment'], (string) $row['remaining']) > 0) {
            $e[] = 'The installment cannot be more than the remaining balance.';
        }
        if (! $this->validMonth($row['start_month'])) {
            $e[] = 'Start month must look like 2026-12.';
        }

        return $e;
    }

    public function post(OpeningBatch $batch, Collection $rows): array
    {
        foreach ($rows as $row) {
            $d = $row->data;
            $loan = EmployeeLoan::query()->create([
                'number' => app(DocumentNumber::class)->next('employee_loan', $batch->batch_date), 'employee_id' => $this->party(Employee::class, $d['employee_code'])->getKey(),
                'loan_date' => $batch->batch_date, 'amount' => Decimal::round((string) $d['remaining']), 'money_account_id' => MoneyAccount::cashBook()->id,
                'installment' => Decimal::round((string) $d['installment']), 'start_month' => $this->month((string) $d['start_month'])->format('Y-m-d'),
                'notes' => "Opening balance from {$batch->number}", 'created_by' => auth()->id(),
            ]);
            app(LoansAndAdvances::class)->postOpening($loan->refresh(), 'opening loan balance');
            $row->update(['result_type' => $loan->getMorphClass(), 'result_id' => $loan->id]);
            $this->add('PKR', (string) $d['remaining']);
        }

        return $this->takeTotals();
    }
}
