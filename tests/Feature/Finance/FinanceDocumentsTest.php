<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\Deliveries;
use App\Modules\Delivery\Services\Invoices;
use App\Modules\Delivery\Services\Packing;
use App\Modules\Finance\Actions\PostPayment;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Finance\Services\MoneyAccounts;
use App\Modules\Finance\Services\MoneyMovements;
use App\Modules\Finance\Services\Receipts;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TestDocument;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->wf = app(DocumentWorkflow::class);
    $this->ledgers = app(Ledgers::class);
    $this->moves = app(MoneyMovements::class);
    $this->receipts = app(Receipts::class);
    $this->usd = app(MoneyAccounts::class)->create(['name' => 'Meezan USD', 'type' => MoneyAccount::BANK, 'currency_code' => 'USD']);
    $this->bank = app(MoneyAccounts::class)->create(['name' => 'Meezan PKR', 'type' => MoneyAccount::BANK, 'currency_code' => 'PKR']);
    $this->cash = MoneyAccount::cashBook();
    $this->customer = Customer::factory()->create(['name' => 'Hamburg Sports', 'currency_code' => 'USD']);
});

/** A posted USD invoice of $total for the test customer. */
function usdInvoice(string $total = '1000'): Invoice
{
    $variant = Item::factory()->finished()->create()->variants()->first();
    DB::transaction(function () use ($variant) {
        $doc = TestDocument::create(['number' => 'TD-'.uniqid(), 'document_date' => '2026-11-01']);
        $lot = app(StockLedger::class)->createLot($variant->id, 'purchased', '100', Carbon::parse('2026-11-01'));
        app(StockLedger::class)->receive(new StockBucket($variant->id, null, StockStatus::AVAILABLE, Location::mainStore()->id, $lot->id), '10', new StockSource($doc, Carbon::parse('2026-11-01')));
    });
    $order = app(DocumentWorkflow::class)->approve(app(SalesOrders::class)->save(null, ['customer_id' => test()->customer->id, 'order_date' => '2026-11-01'], [[
        'item_variant_id' => $variant->id, 'description' => '', 'quantity' => '10', 'unit_price' => Decimal::div($total, '10', 4), 'tax_rate' => '', 'due_date' => null]]));
    $d = app(Deliveries::class)->allocate(app(Deliveries::class)->save(null, ['customer_id' => test()->customer->id, 'delivery_date' => '2026-11-05'], [['sales_order_line_id' => $order->lines()->first()->id, 'quantity' => '10']]));
    app(Packing::class)->addCarton($d, [], [$d->lines()->value('id') => '10']);
    app(Packing::class)->complete($d);
    $d = app(Deliveries::class)->dispatch($d->fresh());
    $inv = app(Invoices::class)->save(null, ['customer_id' => test()->customer->id, 'invoice_date' => '2026-11-06'], [['delivery_line_id' => $d->lines()->value('id')]]);

    return app(Invoices::class)->post(app(DocumentWorkflow::class)->approve($inv));
}

it('clears a $1,000 invoice with $800 received and $200 deductions, in USD', function () {
    $invoice = usdInvoice('1000');
    expect($this->ledgers->partyBalance($this->customer))->toBe('1000.00');

    $receipt = $this->receipts->save(null, ['receipt_date' => '2026-11-20', 'customer_id' => $this->customer->id, 'amount' => '1000', 'money_account_id' => $this->usd->id],
        [['gl_account_id' => GlAccount::system('tax_deducted')->id, 'amount' => '150'], ['gl_account_id' => GlAccount::system('bank_charges')->id, 'amount' => '50']],
        [['type' => $invoice->getMorphClass(), 'id' => $invoice->id, 'amount' => '1000']]);
    expect($receipt->net_received)->toBe('800.00');

    $this->receipts->post($this->wf->approve($receipt));

    expect($invoice->fresh()->balance())->toBe('0.00')
        ->and($this->ledgers->partyBalance($this->customer))->toBe('0.00')
        ->and($this->ledgers->moneyBalance($this->usd))->toBe('800.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('tax_deducted'), 'USD'))->toBe('150.00');
});

it('keeps an unallocated receipt as a customer advance and applies it later', function () {
    $invoice = usdInvoice('600');
    $receipt = $this->receipts->post($this->wf->approve($this->receipts->save(null, ['receipt_date' => '2026-11-20', 'customer_id' => $this->customer->id, 'amount' => '1000', 'money_account_id' => $this->usd->id], [], [])));

    expect($receipt->unallocated())->toBe('1000.00')
        ->and($this->ledgers->partyBalance($this->customer))->toBe('-400.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('customer_advances'), 'USD'))->toBe('-1000.00');

    expect(fn () => $this->receipts->applyAdvance($receipt, $invoice, '700', Carbon::parse('2026-11-21')))->toThrow(DocumentException::class, 'left to pay');
    $this->receipts->applyAdvance($receipt, $invoice, '600', Carbon::parse('2026-11-21'));

    expect($invoice->fresh()->balance())->toBe('0.00')->and($receipt->fresh()->unallocated())->toBe('400.00')
        ->and($this->ledgers->partyBalance($this->customer))->toBe('-400.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('customer_advances'), 'USD'))->toBe('-400.00');
});

it('refuses a receipt into an account of another currency, and deductions above the amount', function () {
    expect(fn () => $this->receipts->save(null, ['receipt_date' => '2026-11-20', 'customer_id' => $this->customer->id, 'amount' => '10', 'money_account_id' => $this->bank->id], [], []))
        ->toThrow(DocumentException::class, 'USD account');
    expect(fn () => $this->receipts->save(null, ['receipt_date' => '2026-11-20', 'customer_id' => $this->customer->id, 'amount' => '10', 'money_account_id' => $this->usd->id],
        [['gl_account_id' => GlAccount::system('bank_charges')->id, 'amount' => '11']], []))->toThrow(DocumentException::class, 'Deductions');
});

it('converts $800 using the PKR amount typed in, without touching the customer ledger', function () {
    $this->receipts->post($this->wf->approve($this->receipts->save(null, ['receipt_date' => '2026-11-20', 'customer_id' => $this->customer->id, 'amount' => '1000', 'money_account_id' => $this->usd->id], [], [])));
    $customerBefore = $this->ledgers->partyBalance($this->customer);

    expect(fn () => $this->moves->postConversion($this->wf->approve($this->moves->saveConversion(null, ['conversion_date' => '2026-11-22', 'from_account_id' => $this->usd->id, 'from_amount' => '1200', 'to_account_id' => $this->bank->id, 'pkr_received' => '1']))))
        ->toThrow(DocumentException::class, 'cannot pay');

    $c = $this->moves->saveConversion(null, ['conversion_date' => '2026-11-22', 'from_account_id' => $this->usd->id, 'from_amount' => '800', 'to_account_id' => $this->bank->id, 'pkr_received' => '224000', 'deductions' => '500']);
    expect($c->effectiveRate())->toBe('280.6250');
    $this->moves->postConversion($this->wf->approve($c));

    expect($this->ledgers->moneyBalance($this->usd))->toBe('200.00')
        ->and($this->ledgers->moneyBalance($this->bank))->toBe('224000.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('foreign_receipts_realized'), 'PKR'))->toBe('-224500.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('bank_charges'), 'PKR'))->toBe('500.00')
        ->and($this->ledgers->partyBalance($this->customer))->toBe($customerBefore);

    expect(fn () => $this->moves->saveConversion(null, ['conversion_date' => '2026-11-22', 'from_account_id' => $this->bank->id, 'from_amount' => '1', 'to_account_id' => $this->bank->id, 'pkr_received' => '1']))
        ->toThrow(DocumentException::class, 'foreign-currency');
});

it('moves money between accounts with a transfer, changing both', function () {
    $seed = $this->moves->saveVoucher(null, ['voucher_date' => '2026-11-01', 'description' => 'Owner capital'], [
        ['account' => 'money:'.$this->bank->id, 'debit' => '500000'], ['account' => 'gl:'.GlAccount::system('owner_capital')->id, 'credit' => '500000'],
    ]);
    $this->moves->postVoucher($this->wf->approve($seed));

    $t = $this->moves->postTransfer($this->wf->approve($this->moves->saveTransfer(null, ['transfer_date' => '2026-11-02', 'from_account_id' => $this->bank->id, 'to_account_id' => $this->cash->id, 'amount' => '200000'])));

    expect($t->status)->toBe('posted')
        ->and($this->ledgers->moneyBalance($this->bank))->toBe('300000.00')
        ->and($this->ledgers->moneyBalance($this->cash))->toBe('200000.00');
    expect(fn () => $this->moves->saveTransfer(null, ['transfer_date' => '2026-11-02', 'from_account_id' => $this->usd->id, 'to_account_id' => $this->cash->id, 'amount' => '1']))
        ->toThrow(DocumentException::class, 'Currency Conversion');
    expect(fn () => $this->moves->postTransfer($this->wf->approve($this->moves->saveTransfer(null, ['transfer_date' => '2026-11-02', 'from_account_id' => $this->cash->id, 'to_account_id' => $this->bank->id, 'amount' => '300000']))))
        ->toThrow(DocumentException::class, 'cannot pay');
});

it('does not save or post a journal voucher unless it balances, and tags parties', function () {
    $employee = Employee::factory()->create();
    expect(fn () => $this->moves->saveVoucher(null, ['voucher_date' => '2026-11-03', 'description' => 'Damage'], [
        ['account' => 'gl:'.GlAccount::system('wages_payable')->id, 'party' => $employee->getMorphClass().':'.$employee->id, 'debit' => '5000'],
        ['account' => 'gl:'.GlAccount::system('damage_recoveries')->id, 'credit' => '4000'],
    ]))->toThrow(DocumentException::class, 'does not balance');

    $jv = $this->moves->saveVoucher(null, ['voucher_date' => '2026-11-03', 'description' => 'Jacket damaged'], [
        ['account' => 'gl:'.GlAccount::system('wages_payable')->id, 'party' => $employee->getMorphClass().':'.$employee->id, 'debit' => '5000', 'deduct_from_payroll' => true],
        ['account' => 'gl:'.GlAccount::system('damage_recoveries')->id, 'credit' => '5000'],
    ]);
    $this->moves->postVoucher($this->wf->approve($jv));

    expect($jv->fresh()->journal_id)->not->toBeNull()
        ->and($this->ledgers->partyBalance($employee))->toBe('5000.00')
        ->and($jv->lines()->where('deduct_from_payroll', true)->count())->toBe(1);
    expect(fn () => $jv->fresh()->update(['description' => 'x']))->toThrow(DocumentException::class);
});

it('posts expenses paid now or payable to a vendor, and pays the payable later', function () {
    $this->moves->postVoucher($this->wf->approve($this->moves->saveVoucher(null, ['voucher_date' => '2026-11-01', 'description' => 'Float'], [
        ['account' => 'money:'.$this->cash->id, 'debit' => '10000'], ['account' => 'gl:'.GlAccount::system('owner_capital')->id, 'credit' => '10000']])));
    $rent = GlAccount::system('rent');
    $vendor = Vendor::factory()->create();

    $this->moves->postExpense($this->wf->approve($this->moves->saveExpense(null, ['expense_date' => '2026-11-05', 'expense_account_id' => $rent->id, 'money_account_id' => $this->cash->id, 'amount' => '3000'])));
    $payable = $this->moves->postExpense($this->wf->approve($this->moves->saveExpense(null, ['expense_date' => '2026-11-05', 'expense_account_id' => GlAccount::system('utilities')->id, 'vendor_id' => $vendor->id, 'amount' => '2500'])));

    expect($this->ledgers->moneyBalance($this->cash))->toBe('7000.00')
        ->and($this->ledgers->accountBalance($rent))->toBe('3000.00')
        ->and($this->ledgers->partyBalance($vendor))->toBe('-2500.00')
        ->and($payable->payableBalance())->toBe('2500.00');

    $payment = Payment::query()->create(['number' => 'PAY-T-1', 'payment_date' => '2026-11-06', 'payee_kind' => 'vendor', 'payee_type' => $vendor->getMorphClass(), 'payee_id' => $vendor->id, 'money_account_id' => $this->cash->id, 'amount' => '2500']);
    app(PostPayment::class)($this->wf->approve($payment), [['allocatable' => $payable, 'amount' => '2500']]);
    expect(Expense::query()->find($payable->id)->payableBalance())->toBe('0.00')->and($this->ledgers->partyBalance($vendor))->toBe('0.00');

    expect(fn () => $this->moves->saveExpense(null, ['expense_date' => '2026-11-05', 'expense_account_id' => $rent->id, 'amount' => '1']))->toThrow(DocumentException::class, 'paid from');
    expect(fn () => $this->moves->saveExpense(null, ['expense_date' => '2026-11-05', 'expense_account_id' => GlAccount::system('sales')->id, 'money_account_id' => $this->cash->id, 'amount' => '1']))->toThrow(DocumentException::class, 'not an expense head');
});
