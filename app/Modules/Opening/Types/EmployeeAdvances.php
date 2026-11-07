<?php

namespace App\Modules\Opening\Types;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\OpeningContext;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\LoansAndAdvances;
use App\Modules\People\Models\Employee;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/** Salary advances not yet recovered: Dr Employee Advances, Cr Opening Balance. */
class EmployeeAdvances extends BaseType
{
    public function key(): string
    {
        return 'employee_advances';
    }

    public function label(): string
    {
        return 'Employee advances';
    }

    public function columns(): array
    {
        return ['employee_code' => 'Employee code', 'remaining' => 'Amount not yet recovered (PKR)', 'salary_month' => 'Salary month to deduct from (yyyy-mm)'];
    }

    public function example(): array
    {
        return ['employee_code' => 'EMP-0001', 'remaining' => '5000', 'salary_month' => '2026-11'];
    }

    public function validate(array $row, OpeningContext $context): array
    {
        $e = [];
        if (! $this->party(Employee::class, $row['employee_code'])) {
            $e[] = "Unknown employee \"{$row['employee_code']}\".";
        }
        if (! $this->positive($row['remaining'])) {
            $e[] = 'Amount must be above zero.';
        }
        if (! $this->validMonth($row['salary_month'])) {
            $e[] = 'Salary month must look like 2026-11.';
        }

        return $e;
    }

    public function post(OpeningBatch $batch, Collection $rows): array
    {
        foreach ($rows as $row) {
            $d = $row->data;
            $advance = SalaryAdvance::query()->create([
                'number' => app(DocumentNumber::class)->next('salary_advance', $batch->batch_date), 'employee_id' => $this->party(Employee::class, $d['employee_code'])->getKey(),
                'advance_date' => $batch->batch_date, 'amount' => Decimal::round((string) $d['remaining']), 'salary_month' => $this->month((string) $d['salary_month'])->format('Y-m-d'),
                'money_account_id' => MoneyAccount::cashBook()->id, 'notes' => "Opening balance from {$batch->number}", 'created_by' => auth()->id(),
            ]);
            app(LoansAndAdvances::class)->postOpening($advance->refresh(), 'opening advance balance');
            $row->update(['result_type' => $advance->getMorphClass(), 'result_id' => $advance->id]);
            $this->add('PKR', (string) $d['remaining']);
        }

        return $this->takeTotals();
    }
}
