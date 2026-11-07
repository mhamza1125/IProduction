<?php

use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\MoneyMovements;
use App\Modules\Payroll\Livewire\Advances;
use App\Modules\Payroll\Livewire\AttendanceGrid;
use App\Modules\Payroll\Livewire\Loans;
use App\Modules\Payroll\Livewire\Runs;
use App\Modules\Payroll\Models\AttendanceSummary;
use App\Modules\Payroll\Models\EmployeeLoan;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\Payrolls;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Livewire\WageWork;
use App\Support\Documents\DocumentWorkflow;
use Livewire\Livewire;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $moves = app(MoneyMovements::class);
    $moves->postVoucher(app(DocumentWorkflow::class)->approve($moves->saveVoucher(null, ['voucher_date' => '2026-10-01', 'description' => 'Float'], [
        ['account' => 'money:'.MoneyAccount::cashBook()->id, 'debit' => '500000'], ['account' => 'gl:'.GlAccount::system('owner_capital')->id, 'credit' => '500000']])));
    $this->emp = Employee::factory()->create(['name' => 'Sana', 'employee_type' => Employee::TYPE_SALARY, 'monthly_salary' => '30000']);
});

it('runs payroll from the screens: attendance, loan, advance, run, edit, post, slip', function () {
    Livewire::test(AttendanceGrid::class)->set('month', '2026-11')->set("rows.{$this->emp->id}.unpaid_leaves", '1')->call('save');
    expect(AttendanceSummary::query()->sole()->unpaid_leaves)->toBe('1.00');

    Livewire::test(Loans\Form::class)->set('employee_id', $this->emp->id)->set('amount', '20000')->set('installment', '5000')->set('start_month', '2026-11')->call('save')->assertHasNoErrors();
    $loan = EmployeeLoan::query()->sole();
    Livewire::test(Loans\Show::class, ['loan' => $loan])->call('approveDocument')->call('postDocumentAction');
    Livewire::test(Advances\Form::class)->set('employee_id', $this->emp->id)->set('amount', '3000')->set('salary_month', '2026-11')->call('save')->assertHasNoErrors();
    $adv = SalaryAdvance::query()->sole();
    Livewire::test(Advances\Show::class, ['advance' => $adv])->call('approveDocument')->call('postDocumentAction');

    Livewire::test(Runs\Index::class)->set('newMonth', '2026-11')->call('createRun')->assertRedirect();
    $run = PayrollRun::query()->sole();
    $line = $run->lines()->sole();
    // 30,000 − 1,000 (1 unpaid day) − 5,000 loan − 3,000 advance.
    expect($line->net_payable)->toBe('21000.00');

    Livewire::test(Runs\Show::class, ['run' => $run])->call('edit', $line->id)->set('allowances', '500')->call('saveLine')->assertHasNoErrors()
        ->call('approveDocument')->call('postDocumentAction');
    expect($run->fresh()->status)->toBe('posted')->and($run->fresh()->total_net)->toBe('21500.00');

    $this->get(route('payroll.runs.slip', [$run, $run->lines()->sole()]))->assertOk()->assertSee('21,500.00');
    $this->get(route('payroll.runs.print', $run))->assertOk();
});

it('renders the payroll screens', function () {
    $run = app(Payrolls::class)->create(now()->startOfMonth());
    foreach ([route('payroll.attendance'), route('payroll.runs.index'), route('payroll.runs.show', $run), route('payroll.loans.index'), route('payroll.loans.create'),
        route('payroll.advances.index'), route('payroll.advances.create'), route('core.settings.payroll'), route('production.wage-work')] as $url) {
        $this->get($url)->assertOk();
    }
});

it('hides salaries and protects payroll actions', function () {
    $run = app(Payrolls::class)->create(now()->startOfMonth());
    $viewer = userWith(['payroll.view']);

    $this->actingAs(userWith([]))->get(route('payroll.runs.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('payroll.runs.show', $run))->assertOk()->assertSee('Salary amounts are hidden')->assertDontSee('30,000.00');
    $this->actingAs(userWith(['payroll.view', 'payroll.print']))->get(route('payroll.runs.print', $run))->assertForbidden();
    $this->actingAs($viewer)->get(route('payroll.loans.index'))->assertForbidden();

    Livewire::actingAs($viewer)->test(Runs\Index::class)->call('createRun')->assertForbidden();
    Livewire::actingAs($viewer)->test(Runs\Show::class, ['run' => $run])->call('recalculate')->assertForbidden();
    Livewire::actingAs($viewer)->test(Runs\Show::class, ['run' => $run])->call('postDocumentAction')->assertForbidden();
    Livewire::actingAs(userWith(['attendance.view']))->test(AttendanceGrid::class)->call('save')->assertForbidden();
    Livewire::actingAs(userWith(['loans.view']))->test(Loans\Form::class)->call('save')->assertForbidden();
    Livewire::actingAs(userWith(['wage_work.view']))->test(WageWork\Index::class)->call('addManual')->assertForbidden();
});

it('validates payroll forms', function () {
    Livewire::test(Loans\Form::class)->set('amount', '1000')->set('installment', '2000')->set('employee_id', $this->emp->id)->call('save')->assertHasErrors('installment');
    Livewire::test(Loans\Form::class)->call('save')->assertHasErrors(['employee_id', 'amount', 'installment']);
    Livewire::test(Advances\Form::class)->call('save')->assertHasErrors(['employee_id', 'amount']);
    Livewire::test(AttendanceGrid::class)->set("rows.{$this->emp->id}.late_hours", '-1')->call('save');
    expect(AttendanceSummary::query()->count())->toBe(0);
    Livewire::test(WageWork\Index::class)->call('addManual')->assertHasErrors(['new_employee_id', 'new_description', 'new_quantity', 'new_rate']);
});
