<?php

namespace App\Modules\Payroll;

use App\Modules\Finance\Services\Payables;
use App\Modules\Payroll\Models\AttendanceSummary;
use App\Modules\Payroll\Models\EmployeeLoan;
use App\Modules\Payroll\Models\PayrollLine;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\Attendance;
use App\Modules\Payroll\Services\LoansAndAdvances;
use App\Modules\Payroll\Services\Payrolls;
use App\Modules\People\Models\Employee;
use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Attendance and payroll (specification, Module 11).
 */
class PayrollServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Payroll';
    }

    public function register(): void
    {
        parent::register();

        foreach ([Attendance::class, LoansAndAdvances::class, Payrolls::class] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        parent::boot();

        // Payments to an employee can be allocated to posted payroll lines.
        Payables::register('payroll.lines', fn ($payee) => $payee instanceof Employee
            ? PayrollLine::query()->where('employee_id', $payee->id)->whereNotNull('posted_key')->whereColumn('paid_amount', '<', 'net_payable')->orderBy('month')->get()
            : collect());

        Employee::whenUsed('payroll.employee', fn (Employee $e) => PayrollLine::query()->where('employee_id', $e->id)->exists()
            || EmployeeLoan::query()->where('employee_id', $e->id)->exists() || SalaryAdvance::query()->where('employee_id', $e->id)->exists()
            || AttendanceSummary::query()->where('employee_id', $e->id)->exists());
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('attendance', 'Attendance', ['view', 'create', 'edit', 'export'], 'HR'),
            new PermissionSet('loans', 'Employee loans', [...PermissionSet::DOCUMENT, 'override'], 'HR'),
            new PermissionSet('advances', 'Salary advances', PermissionSet::DOCUMENT, 'HR'),
            new PermissionSet('payroll', 'Payroll', [...PermissionSet::DOCUMENT, 'override'], 'HR'),
        ];
    }

    public function documentTypes(): array
    {
        return [
            new DocumentType('payroll', 'Payroll', 'PR'),
            new DocumentType('employee_loan', 'Employee Loan', 'LN'),
            new DocumentType('salary_advance', 'Salary Advance', 'ADV'),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Attendance', 'payroll.attendance', 'calendar', 'attendance.view', 'HR', 700),
            new NavItem('Payroll', 'payroll.runs.index', 'banknotes', 'payroll.view', 'HR', 710, 'payroll.runs.*'),
            new NavItem('Loans', 'payroll.loans.index', 'briefcase', 'loans.view', 'HR', 720, 'payroll.loans.*'),
            new NavItem('Salary Advances', 'payroll.advances.index', 'currency', 'advances.view', 'HR', 730, 'payroll.advances.*'),
        ];
    }
}
