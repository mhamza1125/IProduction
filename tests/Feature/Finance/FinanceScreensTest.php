<?php

use App\Modules\Finance\Livewire\Assets;
use App\Modules\Finance\Livewire\Conversions;
use App\Modules\Finance\Livewire\Expenses;
use App\Modules\Finance\Livewire\Receipts;
use App\Modules\Finance\Livewire\Transfers;
use App\Modules\Finance\Livewire\Vouchers;
use App\Modules\Finance\Models\Asset;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\JournalVoucher;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Services\MoneyAccounts;
use App\Modules\Finance\Services\MoneyMovements;
use App\Modules\Finance\Services\Receipts as ReceiptService;
use App\Modules\People\Models\Customer;
use App\Support\Documents\DocumentWorkflow;
use Livewire\Livewire;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->usd = app(MoneyAccounts::class)->create(['name' => 'USD Bank', 'type' => MoneyAccount::BANK, 'currency_code' => 'USD']);
    $this->customer = Customer::factory()->create(['currency_code' => 'USD']);
    $this->cash = MoneyAccount::cashBook();
});

it('records a receipt and a balanced voucher from the screens', function () {
    Livewire::test(Receipts\Form::class)
        ->set('customer_id', $this->customer->id)->set('amount', '500')->set('money_account_id', $this->usd->id)
        ->call('addDeduction')->set('deductions.0.gl_account_id', GlAccount::system('bank_charges')->id)->set('deductions.0.amount', '20')
        ->assertSee('480.00')
        ->call('save')->assertHasNoErrors()->assertRedirect();
    $receipt = Receipt::query()->sole();
    Livewire::test(Receipts\Show::class, ['receipt' => $receipt])->call('approveDocument')->call('postDocumentAction')->assertSee('Unallocated');
    expect($receipt->fresh()->isPosted())->toBeTrue();

    Livewire::test(Vouchers\Form::class)
        ->set('description', 'Opening float')
        ->set('lines.0.account', 'money:'.$this->cash->id)->set('lines.0.debit', '1000')
        ->set('lines.1.account', 'gl:'.GlAccount::system('owner_capital')->id)->set('lines.1.credit', '900')
        ->call('save')->assertHasErrors('lines')
        ->set('lines.1.credit', '1000')->call('save')->assertHasNoErrors()->assertRedirect();
    expect(JournalVoucher::query()->sole()->total)->toBe('1000.00');
});

it('renders every finance screen and print', function () {
    $moves = app(MoneyMovements::class);
    $wf = app(DocumentWorkflow::class);
    $jv = $moves->postVoucher($wf->approve($moves->saveVoucher(null, ['voucher_date' => '2026-11-01', 'description' => 'Float'], [
        ['account' => 'money:'.$this->cash->id, 'debit' => '5000'], ['account' => 'gl:'.GlAccount::system('owner_capital')->id, 'credit' => '5000']])));
    $receipt = app(ReceiptService::class)->post($wf->approve(app(ReceiptService::class)->save(null, ['receipt_date' => '2026-11-02', 'customer_id' => $this->customer->id, 'amount' => '100', 'money_account_id' => $this->usd->id], [], [])));
    $conv = $moves->saveConversion(null, ['conversion_date' => '2026-11-03', 'from_account_id' => $this->usd->id, 'from_amount' => '50', 'to_account_id' => $this->cash->id, 'pkr_received' => '14000']);
    $bank = app(MoneyAccounts::class)->create(['name' => 'PKR Bank', 'type' => MoneyAccount::BANK, 'currency_code' => 'PKR']);
    $transfer = $moves->saveTransfer(null, ['transfer_date' => '2026-11-03', 'from_account_id' => $this->cash->id, 'to_account_id' => $bank->id, 'amount' => '100']);
    $expense = $moves->saveExpense(null, ['expense_date' => '2026-11-03', 'expense_account_id' => GlAccount::system('rent')->id, 'money_account_id' => $this->cash->id, 'amount' => '100']);
    $asset = Asset::query()->create(['code' => 'AST-1', 'name' => 'Honda bike', 'status' => 'in_use']);

    foreach ([
        route('finance.receipts.index'), route('finance.receipts.create'), route('finance.receipts.show', $receipt), route('finance.receipts.print', $receipt),
        route('finance.conversions.index'), route('finance.conversions.create'), route('finance.conversions.show', $conv), route('finance.conversions.edit', $conv),
        route('finance.transfers.index'), route('finance.transfers.create'), route('finance.transfers.show', $transfer),
        route('finance.expenses.index'), route('finance.expenses.create'), route('finance.expenses.show', $expense),
        route('finance.vouchers.index'), route('finance.vouchers.create'), route('finance.vouchers.show', $jv), route('finance.vouchers.print', $jv),
        route('finance.assets.index'), route('finance.assets.create'), route('finance.assets.edit', $asset),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('protects finance screens, prints and actions', function () {
    $viewer = userWith(['receipts.view']);
    $this->actingAs(userWith([]))->get(route('finance.receipts.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('finance.receipts.index'))->assertOk();
    $this->actingAs($viewer)->get(route('finance.receipts.create'))->assertForbidden();
    $this->actingAs($viewer)->get(route('finance.vouchers.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('finance.assets.index'))->assertForbidden();

    Livewire::actingAs($viewer)->test(Receipts\Form::class)->call('save')->assertForbidden();
    Livewire::actingAs(userWith(['journal_vouchers.view']))->test(Vouchers\Form::class)->call('save')->assertForbidden();
    Livewire::actingAs(userWith(['transfers.view']))->test(Transfers\Form::class)->call('save')->assertForbidden();
    Livewire::actingAs(userWith(['expenses.view']))->test(Expenses\Form::class)->call('save')->assertForbidden();
    Livewire::actingAs(userWith(['currency_conversions.view']))->test(Conversions\Form::class)->call('save')->assertForbidden();
    Livewire::actingAs(userWith(['assets.view']))->test(Assets\Form::class)->call('save')->assertForbidden();
});

it('validates the finance forms', function () {
    Livewire::test(Receipts\Form::class)->call('save')->assertHasErrors(['customer_id', 'amount', 'money_account_id']);
    Livewire::test(Conversions\Form::class)->call('save')->assertHasErrors(['from_account_id', 'from_amount', 'to_account_id', 'pkr_received']);
    Livewire::test(Transfers\Form::class)->set('from_account_id', $this->cash->id)->set('to_account_id', $this->cash->id)->call('save')->assertHasErrors(['to_account_id', 'amount']);
    Livewire::test(Expenses\Form::class)->call('save')->assertHasErrors(['expense_account_id', 'money_account_id', 'amount']);
    Livewire::test(Expenses\Form::class)->set('payment', 'payable')->call('save')->assertHasErrors(['vendor_id']);
    Livewire::test(Vouchers\Form::class)->call('save')->assertHasErrors(['description']);
    Livewire::test(Assets\Form::class)->call('save')->assertHasErrors(['name']);
    Livewire::test(Assets\Form::class)->set('name', 'Split AC')->call('save')->assertHasNoErrors();
    expect(Asset::query()->sole()->code)->toStartWith('AST-');
});
