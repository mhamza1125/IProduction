<?php

use App\Modules\Core\Services\Settings;
use App\Modules\Finance\Actions\PostPayment;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Finance\Services\MoneyMovements;
use App\Modules\Payroll\Models\EmployeeLoan;
use App\Modules\Payroll\Models\PayrollLine;
use App\Modules\Payroll\Services\Attendance;
use App\Modules\Payroll\Services\LoansAndAdvances;
use App\Modules\Payroll\Services\Payrolls;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Models\WageWork;
use App\Modules\Production\Services\WageWorks;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->wf = app(DocumentWorkflow::class);
    $this->payrolls = app(Payrolls::class);
    $this->loans = app(LoansAndAdvances::class);
    $this->ledgers = app(Ledgers::class);
    $this->cash = MoneyAccount::cashBook();
    $this->nov = Carbon::parse('2026-11-01');
    // Money in the Cash Book to pay loans, advances and salaries from.
    $moves = app(MoneyMovements::class);
    $moves->postVoucher($this->wf->approve($moves->saveVoucher(null, ['voucher_date' => '2026-10-01', 'description' => 'Float'], [
        ['account' => 'money:'.$this->cash->id, 'debit' => '1000000'], ['account' => 'gl:'.GlAccount::system('owner_capital')->id, 'credit' => '1000000']])));
    $this->ali = Employee::factory()->create(['name' => 'Ali', 'employee_type' => Employee::TYPE_SALARY, 'monthly_salary' => '50000']);
});

function loan(Employee $e, string $amount, string $installment, string $start = '2026-11-01'): EmployeeLoan
{
    return app(LoansAndAdvances::class)->postLoan(app(DocumentWorkflow::class)->approve(app(LoansAndAdvances::class)->saveLoan(null, [
        'employee_id' => $e->id, 'loan_date' => '2026-10-15', 'amount' => $amount, 'money_account_id' => MoneyAccount::cashBook()->id, 'installment' => $installment, 'start_month' => $start])));
}

function advance(Employee $e, string $amount, string $month = '2026-11-01'): void
{
    app(LoansAndAdvances::class)->postAdvance(app(DocumentWorkflow::class)->approve(app(LoansAndAdvances::class)->saveAdvance(null, [
        'employee_id' => $e->id, 'advance_date' => '2026-11-10', 'amount' => $amount, 'salary_month' => $month, 'money_account_id' => MoneyAccount::cashBook()->id])));
}

function runFor(Carbon $month, ?string $type = null)
{
    return app(Payrolls::class)->create($month, null, $type);
}

function postRun($run)
{
    return app(Payrolls::class)->post(app(DocumentWorkflow::class)->approve($run));
}

it('pays 15,000 on a 50,000 salary with a 10,000 loan installment and a 25,000 advance', function () {
    loan($this->ali, '100000', '10000');
    advance($this->ali, '25000');

    $run = runFor($this->nov);
    $line = $run->lines()->where('employee_id', $this->ali->id)->sole();
    expect($line->earnings)->toBe('50000.00')->and($line->loan_installment)->toBe('10000.00')
        ->and($line->advance_deduction)->toBe('25000.00')->and($line->net_payable)->toBe('15000.00');

    // Nothing permanent before posting.
    expect(EmployeeLoan::query()->sole()->recovered_amount)->toBe('0.00');

    postRun($run);
    expect(EmployeeLoan::query()->sole()->balance())->toBe('90000.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('salaries')))->toBe('50000.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('employee_advances')))->toBe('0.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('employee_loans')))->toBe('90000.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('wages_payable')))->toBe('-15000.00');
});

it('cannot post payroll twice for one employee and month', function () {
    $first = runFor($this->nov);
    $second = runFor($this->nov);
    postRun($first);

    expect(fn () => postRun($second))->toThrow(DocumentException::class, 'already has a posted payroll');
    expect(runFor($this->nov)->lines()->count())->toBe(0);
    expect(PayrollLine::query()->whereNotNull('posted_key')->count())->toBe(1);
});

it('stops loan installments when the balance reaches zero, the last one capped at what is left', function () {
    $loan = loan($this->ali, '25000', '10000');

    foreach (['2026-11-01' => '10000.00', '2026-12-01' => '10000.00', '2027-01-01' => '5000.00', '2027-02-01' => '0.00'] as $month => $expected) {
        $run = runFor(Carbon::parse($month));
        expect($run->lines()->sole()->loan_installment)->toBe($expected);
        postRun($run);
    }
    expect($loan->fresh()->balance())->toBe('0.00')->and($loan->installments()->count())->toBe(3);
});

it('lets an authorised user skip or change an installment with a reason', function () {
    $loan = loan($this->ali, '100000', '10000');
    $this->loans->changeInstallment($loan, Carbon::parse('2026-11-01'), '0', 'Wedding month');
    $this->loans->changeInstallment($loan, Carbon::parse('2026-12-01'), '15000', 'Bonus month');
    expect(fn () => $this->loans->changeInstallment($loan, Carbon::parse('2026-12-01'), '5000', ''))->toThrow(DocumentException::class, 'reason');

    expect(runFor($this->nov)->lines()->sole()->loan_installment)->toBe('0.00')
        ->and(runFor(Carbon::parse('2026-12-01'))->lines()->sole()->loan_installment)->toBe('15000.00');
});

it('deducts unpaid days and late hours and pays overtime to salaried staff only', function () {
    $wage = Employee::factory()->create(['employee_type' => Employee::TYPE_WAGE, 'monthly_salary' => null]);
    app(Attendance::class)->saveMonth($this->nov, [
        $this->ali->id => ['working_days' => '26', 'unpaid_leaves' => '2', 'late_hours' => '4', 'overtime_hours' => '10', 'paid_leaves' => '1'],
        $wage->id => ['overtime_hours' => '20'],
    ]);
    app(Settings::class)->setMany(['payroll.overtime_multiplier' => '1.5']);

    $run = runFor($this->nov);
    $line = $run->lines()->where('employee_id', $this->ali->id)->sole();
    // daily 50,000 ÷ 30 = 1,666.67; hourly = 208.33.
    expect($line->calculated_attendance_deduction)->toBe('4166.66')  // 2 × 1,666.666… + 4 × 208.333…, each rounded once
        ->and($line->overtime)->toBe('3125.00')                       // 10 × 208.33 × 1.5
        ->and($line->net_payable)->toBe('48958.34')
        ->and($run->lines()->where('employee_id', $wage->id)->sole()->overtime)->toBe('0.00');
});

it('edits a calculated deduction only with permission and a reason, and keeps it on recalculation', function () {
    app(Attendance::class)->saveMonth($this->nov, [$this->ali->id => ['unpaid_leaves' => '3']]);
    $run = runFor($this->nov);
    $line = $run->lines()->sole();

    $this->actingAs(userWith(['payroll.edit']));
    expect(fn () => $this->payrolls->editLine($line, '0', '0', 'Sick'))->toThrow(DocumentException::class, 'permission');
    $this->actingAs(admin());
    expect(fn () => $this->payrolls->editLine($line, '0', '0', ''))->toThrow(DocumentException::class, 'reason');

    $line = $this->payrolls->editLine($line, '2000', '1000', 'Two days were sick leave');
    expect($line->attendance_deduction)->toBe('1000.00')->and($line->allowances)->toBe('2000.00')->and($line->net_payable)->toBe('51000.00');

    $line = $this->payrolls->generate($run)->lines()->sole();
    expect($line->attendance_deduction)->toBe('1000.00')->and($line->deduction_override_reason)->toBe('Two days were sick leave');
});

it('pays wage employees for approved wage work once, deducts payroll fines, and carries a negative net forward', function () {
    $karim = Employee::factory()->create(['employee_type' => Employee::TYPE_WAGE, 'monthly_salary' => null]);
    $wages = app(WageWorks::class);
    $w = $wages->recordManual($karim, Carbon::parse('2026-11-05'), 'Packing cartons', '100', '30');
    $pending = $wages->recordManual($karim, Carbon::parse('2026-11-06'), 'Extra packing', '10', '30');
    $wages->approve($w);
    expect($this->ledgers->partyBalance($karim))->toBe('-3000.00');

    $moves = app(MoneyMovements::class);
    $moves->postVoucher($this->wf->approve($moves->saveVoucher(null, ['voucher_date' => '2026-11-20', 'description' => 'Jacket damaged'], [
        ['account' => 'gl:'.GlAccount::system('wages_payable')->id, 'party' => $karim->getMorphClass().':'.$karim->id, 'debit' => '5000', 'deduct_from_payroll' => true],
        ['account' => 'gl:'.GlAccount::system('damage_recoveries')->id, 'credit' => '5000']])));

    $line = runFor($this->nov, Employee::TYPE_WAGE)->lines()->sole();
    expect($line->earnings)->toBe('3000.00')->and($line->fines)->toBe('5000.00')
        ->and($line->net_payable)->toBe('0.00')->and($line->carried_forward)->toBe('2000.00');
    postRun($line->run);

    expect(WageWork::query()->find($w->id)->payroll_line_id)->toBe($line->id)
        ->and(WageWork::query()->find($pending->id)->payroll_line_id)->toBeNull()
        ->and($this->ledgers->partyBalance($karim))->toBe('2000.00');

    // December: the pending work is approved, and the 2,000 brought forward is deducted.
    $wages->approve($pending);
    $dec = runFor(Carbon::parse('2026-12-01'), Employee::TYPE_WAGE)->lines()->sole();
    expect($dec->earnings)->toBe('300.00')->and($dec->brought_forward)->toBe('2000.00')->and($dec->fines)->toBe('0.00')
        ->and($dec->carried_forward)->toBe('1700.00');
});

it('pays net payroll in one or several payments and tracks the run status', function () {
    $run = postRun(runFor($this->nov));
    $line = $run->lines()->sole();
    $pay = function (string $amount) use ($line) {
        $p = Payment::query()->create(['number' => 'PAY-'.uniqid(), 'payment_date' => '2026-12-01', 'payee_kind' => 'employee', 'payee_type' => $this->ali->getMorphClass(),
            'payee_id' => $this->ali->id, 'money_account_id' => $this->cash->id, 'amount' => $amount]);
        app(PostPayment::class)($this->wf->approve($p), [['allocatable' => $line->fresh(), 'amount' => $amount]]);
    };

    $pay('20000');
    expect($run->fresh()->status)->toBe('partly_paid');
    $pay('30000');
    expect($run->fresh()->status)->toBe('paid')->and($line->fresh()->payableBalance())->toBe('0.00')
        ->and($this->ledgers->partyBalance($this->ali))->toBe('0.00');
});

it('locks attendance once the month is posted', function () {
    app(Attendance::class)->saveMonth($this->nov, [$this->ali->id => ['late_hours' => '1']]);
    postRun(runFor($this->nov));

    expect(fn () => app(Attendance::class)->saveMonth($this->nov, [$this->ali->id => ['late_hours' => '5']]))->toThrow(DocumentException::class, 'posted');
});
