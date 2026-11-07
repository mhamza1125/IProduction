<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Core\Services\Settings;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Finance\Services\MoneyMovements;
use App\Modules\Payroll\Models\EmployeeLoan;
use App\Modules\Payroll\Models\LoanInstallment;
use App\Modules\Payroll\Models\PayrollLine;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Services\WageWorks;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Payroll runs (specification, Module 11).
 *
 * Salary employee:   monthly salary + overtime (hours × hourly rate × multiplier)
 *                    − unpaid days × daily rate − late hours × hourly rate
 * Wage employee:     approved wage work not yet paid (no overtime, no attendance deduction)
 * Both:              + allowances − fines − loan installment − advance − amount brought forward
 *   daily rate = salary ÷ salary day basis; hourly rate = daily rate ÷ daily hours.
 * A negative net carries to the next month. Nothing permanent happens before posting.
 *
 * Posting (PKR):  salary + overtime + allowances − attendance: Dr Salaries, Cr Wages and Salaries Payable
 *                 loan installment: Dr Wages Payable, Cr Employee Loans
 *                 advance deducted: Dr Wages Payable, Cr Employee Advances
 *                 wage work was already posted when approved; it is marked paid by this line.
 */
class Payrolls
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly JournalPoster $journals,
        private readonly Settings $settings,
        private readonly Attendance $attendance,
        private readonly WageWorks $wageWorks,
        private readonly MoneyMovements $money,
    ) {}

    public function create(CarbonInterface $month, ?int $departmentId = null, ?string $employeeType = null, ?string $notes = null): PayrollRun
    {
        $month = $month->copy()->startOfMonth();

        return DB::transaction(function () use ($month, $departmentId, $employeeType, $notes) {
            $run = PayrollRun::query()->create([
                'number' => $this->numbers->next('payroll', $month), 'month' => $month->format('Y-m-d'),
                'department_id' => $departmentId, 'employee_type' => $employeeType, 'notes' => $notes, 'created_by' => auth()->id(),
            ]);

            return $this->generate($run->refresh());
        });
    }

    /** (Re)calculates every line of a draft run, keeping allowances and edited deductions. */
    public function generate(PayrollRun $run): PayrollRun
    {
        if (! $run->isDraft()) {
            throw new DocumentException("{$run->number} is {$run->status}; it can no longer be recalculated.");
        }

        return DB::transaction(function () use ($run) {
            $kept = $run->lines()->get()->keyBy('employee_id');
            $run->lines()->delete();

            $employees = Employee::query()->where('is_active', true)
                ->when($run->department_id, fn ($q) => $q->where('department_id', $run->department_id))
                ->when($run->employee_type, fn ($q) => $q->where('employee_type', $run->employee_type))
                ->whereNotIn('id', PayrollLine::query()->whereDate('month', $run->month)->whereNotNull('posted_key')->select('employee_id'))
                ->orderBy('name')->get();

            foreach ($employees as $employee) {
                $old = $kept->get($employee->id);
                $this->calculate($run, $employee, $old?->allowances, $old?->deduction_override_reason !== null ? $old->attendance_deduction : null, $old?->deduction_override_reason);
            }
            $this->refreshTotals($run);

            return $run->refresh();
        });
    }

    /** Edits a draft line: allowances, and the attendance deduction (needs payroll.override and a reason). */
    public function editLine(PayrollLine $line, string $allowances, ?string $attendanceDeduction = null, ?string $reason = null): PayrollLine
    {
        $run = $line->run()->firstOrFail();
        if (! $run->isDraft()) {
            throw new DocumentException("{$run->number} is {$run->status}; lines cannot change.");
        }
        if (Decimal::isNegative($allowances)) {
            throw new DocumentException('Allowances cannot be negative.');
        }
        $override = null;
        if ($attendanceDeduction !== null && Decimal::compare($attendanceDeduction, $line->calculated_attendance_deduction) !== 0) {
            if (! auth()->user()?->can('payroll.override')) {
                throw new DocumentException('Changing a calculated deduction needs permission.');
            }
            if (trim((string) $reason) === '') {
                throw new DocumentException('Give a reason for changing the deduction.');
            }
            if (Decimal::isNegative($attendanceDeduction)) {
                throw new DocumentException('Deductions cannot be negative.');
            }
            $override = $attendanceDeduction;
        }

        return DB::transaction(function () use ($line, $run, $allowances, $override, $reason) {
            $employee = $line->employee()->firstOrFail();
            $line->delete();
            $new = $this->calculate($run, $employee, $allowances, $override, $override !== null ? $reason : null);
            $this->refreshTotals($run);

            return $new;
        });
    }

    private function calculate(PayrollRun $run, Employee $employee, ?string $allowances, ?string $attendanceOverride, ?string $overrideReason): PayrollLine
    {
        $month = Carbon::parse($run->month)->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $items = [];
        $isWage = $employee->employee_type === Employee::TYPE_WAGE;

        // Earnings.
        $earnings = '0.00';
        $overtime = '0.00';
        $calculatedAttendance = '0.00';
        if ($isWage) {
            foreach ($this->wageWorks->unpaidFor($employee, $end) as $w) {
                $label = trim(($w->ptc->number ?? '').' '.($w->stage->name ?? '').' '.($w->description ?? ''));
                $items[] = ['kind' => 'wage_work', 'description' => "{$w->work_date->format('d-m-Y')} {$label}: ".Decimal::round($w->quantity, 4)." × {$w->rate}", 'amount' => $w->amount, 'source' => $w];
                $earnings = Decimal::add($earnings, $w->amount);
            }
        } else {
            $salary = $employee->monthly_salary ?? '0';
            $earnings = Decimal::round($salary);
            $items[] = ['kind' => 'salary', 'description' => 'Monthly salary', 'amount' => $earnings];

            $basis = (string) $this->settings->get('payroll.salary_day_basis', 30);
            $hours = (string) $this->settings->get('payroll.daily_hours', '8');
            $daily = Decimal::of($salary)->dividedBy($basis, 10, RoundingMode::HalfUp);
            $hourly = $daily->dividedBy($hours, 10, RoundingMode::HalfUp);
            $att = $this->attendance->for($employee->id, $month);

            if ($att && Decimal::compare($att->unpaid_leaves ?? '0', '0') > 0 && $this->settings->get('payroll.deduct_unpaid_days', true)) {
                $amount = (string) $daily->multipliedBy($att->unpaid_leaves)->toScale(2, RoundingMode::HalfUp);
                $items[] = ['kind' => 'unpaid_days', 'description' => "Unpaid days {$att->unpaid_leaves} × ".$daily->toScale(2, RoundingMode::HalfUp), 'amount' => '-'.$amount];
                $calculatedAttendance = Decimal::add($calculatedAttendance, $amount);
            }
            if ($att && Decimal::compare($att->late_hours ?? '0', '0') > 0 && $this->settings->get('payroll.deduct_late_hours', true)) {
                $amount = (string) $hourly->multipliedBy($att->late_hours)->toScale(2, RoundingMode::HalfUp);
                $items[] = ['kind' => 'late_hours', 'description' => "Late hours {$att->late_hours} × ".$hourly->toScale(2, RoundingMode::HalfUp), 'amount' => '-'.$amount];
                $calculatedAttendance = Decimal::add($calculatedAttendance, $amount);
            }
            $multiplier = (string) $this->settings->get('payroll.overtime_multiplier', '1');
            if ($att && Decimal::compare($att->overtime_hours ?? '0', '0') > 0 && Decimal::compare($multiplier, '0') > 0) {
                $overtime = (string) $hourly->multipliedBy($att->overtime_hours)->multipliedBy($multiplier)->toScale(2, RoundingMode::HalfUp);
                $items[] = ['kind' => 'overtime', 'description' => "Overtime {$att->overtime_hours} h × ".$hourly->toScale(2, RoundingMode::HalfUp)." × {$multiplier}", 'amount' => $overtime];
            }
        }

        $allowances = Decimal::round($allowances ?? '0');
        if (Decimal::compare($allowances, '0') > 0) {
            $items[] = ['kind' => 'allowance', 'description' => 'Allowances', 'amount' => $allowances];
        }
        $attendanceDeduction = $attendanceOverride !== null ? Decimal::round($attendanceOverride) : $calculatedAttendance;
        if ($attendanceOverride !== null) {
            $items = array_values(array_filter($items, fn ($i) => ! in_array($i['kind'], ['unpaid_days', 'late_hours'], true)));
            $items[] = ['kind' => 'attendance_adjusted', 'description' => "Attendance deduction edited from {$calculatedAttendance}: {$overrideReason}", 'amount' => '-'.$attendanceDeduction];
        }

        // Deductions.
        $fines = '0.00';
        foreach ($this->money->payrollFines($employee, $end) as $f) {
            $items[] = ['kind' => 'fine', 'description' => "{$f->voucher->number} ".($f->memo ?? $f->voucher->description), 'amount' => '-'.$f->debit, 'source' => $f];
            $fines = Decimal::add($fines, $f->debit);
        }

        $loan = '0.00';
        foreach (EmployeeLoan::query()->where('employee_id', $employee->id)->whereNotNull('posted_at')->whereColumn('recovered_amount', '<', 'amount')->orderBy('loan_date')->get() as $l) {
            $due = $l->dueFor($month);
            if (Decimal::compare($due, '0') > 0) {
                $items[] = ['kind' => 'loan', 'description' => "{$l->number} installment (balance {$l->balance()})", 'amount' => '-'.$due, 'source' => $l];
                $loan = Decimal::add($loan, $due);
            }
        }

        $previous = PayrollLine::query()->where('employee_id', $employee->id)->whereNotNull('posted_key')->whereDate('month', '<', $month)->orderByDesc('month')->first();
        $broughtForward = $previous ? Decimal::round($previous->carried_forward) : '0.00';
        if (Decimal::compare($broughtForward, '0') > 0) {
            $items[] = ['kind' => 'brought_forward', 'description' => 'Brought forward from '.$previous->month->format('M Y'), 'amount' => '-'.$broughtForward];
        }

        // Advances for this month (and earlier) reduce what is left; any unused part carries forward on the advance.
        $gross = Decimal::add(Decimal::add($earnings, $overtime), $allowances);
        $available = Decimal::sub(Decimal::sub(Decimal::sub(Decimal::sub($gross, $attendanceDeduction), $fines), $loan), $broughtForward);
        $advance = '0.00';
        foreach (SalaryAdvance::query()->where('employee_id', $employee->id)->whereNotNull('posted_at')->whereDate('salary_month', '<=', $month)
            ->whereColumn('recovered_amount', '<', 'amount')->orderBy('salary_month')->orderBy('id')->get() as $a) {
            $room = Decimal::sub($available, $advance);
            if (Decimal::compare($room, '0') <= 0) {
                break;
            }
            $take = Decimal::compare($a->balance(), $room) < 0 ? $a->balance() : $room;
            $items[] = ['kind' => 'advance', 'description' => "{$a->number} advance of {$a->advance_date->format('d-m-Y')}", 'amount' => '-'.$take, 'source' => $a];
            $advance = Decimal::add($advance, $take);
        }

        $net = Decimal::sub($available, $advance);
        $carried = Decimal::isNegative($net) ? Decimal::sub('0', $net) : '0.00';

        $line = PayrollLine::query()->create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'month' => $month->format('Y-m-d'), 'employee_type' => $employee->employee_type,
            'monthly_salary' => $isWage ? null : $employee->monthly_salary, 'earnings' => $earnings, 'overtime' => $overtime, 'allowances' => $allowances,
            'attendance_deduction' => $attendanceDeduction, 'calculated_attendance_deduction' => $calculatedAttendance, 'deduction_override_reason' => $overrideReason,
            'fines' => $fines, 'loan_installment' => $loan, 'advance_deduction' => $advance, 'brought_forward' => $broughtForward,
            'net_payable' => Decimal::isNegative($net) ? '0.00' : $net, 'carried_forward' => $carried,
        ]);
        foreach ($items as $i) {
            $line->items()->create(['kind' => $i['kind'], 'description' => mb_substr($i['description'], 0, 255), 'amount' => $i['amount'],
                'source_type' => isset($i['source']) ? $i['source']->getMorphClass() : null, 'source_id' => isset($i['source']) ? $i['source']->getKey() : null]);
        }

        return $line;
    }

    public function post(PayrollRun $run, ?string $overrideReason = null): PayrollRun
    {
        try {
            return $this->workflow->post($run, function (PayrollRun $r) {
                $month = Carbon::parse($r->month);
                $date = $month->copy()->endOfMonth();
                foreach ($r->lines()->with(['employee', 'items'])->lockForUpdate()->get() as $line) {
                    $employee = $line->employee;
                    $key = $employee->id.'-'.$month->format('Y-m');
                    if (PayrollLine::query()->where('posted_key', $key)->exists()) {
                        throw new DocumentException("{$employee->name} already has a posted payroll for ".$month->format('M Y').'.');
                    }

                    // Wage work and fines: refuse anything already paid / deducted.
                    $wageIds = $line->items->where('kind', 'wage_work')->pluck('source_id')->map(fn ($id) => (int) $id)->all();
                    if ($wageIds !== []) {
                        $this->wageWorks->markPaid($wageIds, $line->id);
                    }
                    $fineIds = $line->items->where('kind', 'fine')->pluck('source_id')->map(fn ($id) => (int) $id)->all();
                    if ($fineIds !== []) {
                        $this->money->deductFines($fineIds, $line->id, $employee, $date, $r);
                    }

                    $entry = new JournalEntry($date, 'PKR', "{$r->number} payroll ".$month->format('M Y')." {$employee->name}", $r);
                    $salaryExpense = Decimal::sub(Decimal::add(Decimal::add($line->employee_type === Employee::TYPE_WAGE ? '0' : $line->earnings, $line->overtime), $line->allowances), $line->attendance_deduction);
                    if (Decimal::compare($salaryExpense, '0') > 0) {
                        $entry->debit('salaries', $salaryExpense)->credit('wages_payable', $salaryExpense, $employee);
                    }
                    foreach ($line->items->where('kind', 'loan') as $item) {
                        $loan = EmployeeLoan::query()->lockForUpdate()->findOrFail($item->source_id);
                        $amount = Decimal::sub('0', $item->amount);
                        if (Decimal::compare($amount, $loan->balance()) > 0) {
                            throw new DocumentException("{$loan->number}: the installment is more than the remaining balance.");
                        }
                        $loan->forceFill(['recovered_amount' => Decimal::add($loan->recovered_amount, $amount)])->save();
                        LoanInstallment::query()->create(['employee_loan_id' => $loan->id, 'payroll_line_id' => $line->id, 'month' => $month->format('Y-m-d'), 'amount' => $amount]);
                        $entry->debit('wages_payable', $amount, $employee, "{$loan->number} installment")->credit('employee_loans', $amount, $employee);
                    }
                    foreach ($line->items->where('kind', 'advance') as $item) {
                        $advance = SalaryAdvance::query()->lockForUpdate()->findOrFail($item->source_id);
                        $amount = Decimal::sub('0', $item->amount);
                        if (Decimal::compare($amount, $advance->balance()) > 0) {
                            throw new DocumentException("{$advance->number} was already recovered.");
                        }
                        $advance->forceFill(['recovered_amount' => Decimal::add($advance->recovered_amount, $amount)])->save();
                        $entry->debit('wages_payable', $amount, $employee, "{$advance->number} advance")->credit('employee_advances', $amount, $employee);
                    }
                    if ($entry->lines !== []) {
                        $this->journals->post($entry);
                    }
                    $line->forceFill(['posted_key' => $key])->save();
                }
            }, $overrideReason);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'posted_key')) {
                throw new DocumentException('An employee on this payroll already has a posted payroll for the month.');
            }
            throw $e;
        }
    }

    /** Status from what has been paid against the lines: Posted → Partly Paid → Paid. */
    public function refreshPaid(PayrollRun $run): void
    {
        $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
        if (! in_array($run->status, ['posted', 'partly_paid', 'paid'], true)) {
            return;
        }
        $lines = $run->lines()->get();
        $net = Decimal::sum($lines->pluck('net_payable'));
        $paid = Decimal::sum($lines->pluck('paid_amount'));
        $status = Decimal::isZero($paid) ? 'posted' : (Decimal::compare($paid, $net) >= 0 ? 'paid' : 'partly_paid');
        $run->forceFill(['total_paid' => $paid, 'status' => $status])->save();
    }

    private function refreshTotals(PayrollRun $run): void
    {
        $run->forceFill(['total_net' => Decimal::sum($run->lines()->pluck('net_payable'))])->save();
    }
}
