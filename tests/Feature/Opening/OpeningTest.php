<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Services\Settings;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Finance\Services\MoneyAccounts;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Opening\Livewire as Screens;
use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\Openings;
use App\Modules\Payroll\Models\EmployeeLoan;
use App\Modules\Payroll\Services\Payrolls;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Services\PtcTransactions;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Reports\Services\Reports;
use App\Modules\Sales\Models\SalesOrder;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->openings = app(Openings::class);
    $this->ledgers = app(Ledgers::class);
    $this->stock = app(StockLedger::class);
    $this->usdBank = app(MoneyAccounts::class)->create(['name' => 'Meezan USD', 'type' => MoneyAccount::BANK, 'currency_code' => 'USD']);
    $this->customer = Customer::factory()->create(['code' => 'CUS-0100', 'currency_code' => 'USD']);
    $this->vendor = Vendor::factory()->create(['code' => 'VEN-0100']);
    $this->contractor = Contractor::factory()->create(['code' => 'CON-0100']);
    $this->employee = Employee::factory()->create(['code' => 'EMP-0100', 'employee_type' => Employee::TYPE_SALARY, 'monthly_salary' => '40000']);
    $this->stage = Stage::query()->firstOrCreate(['name' => 'Stitching'], ['sort_order' => 2]);
    $item = Item::factory()->finished()->create(['code' => 'GLV', 'can_buy' => true]);
    $this->glove = $item->variants()->first();
    $route = ProductionRoute::create(['item_id' => $item->id, 'name' => 'Std', 'is_default' => true]);
    $route->stages()->create(['stage_id' => Stage::query()->firstOrCreate(['name' => 'Cutting'], ['sort_order' => 1])->id, 'sequence' => 1]);
    $route->stages()->create(['stage_id' => $this->stage->id, 'sequence' => 2]);
    $this->leather = Item::factory()->rawMaterial('kg')->create(['code' => 'LEA'])->variants()->first();
});

/** Writes rows to a CSV in the scratch storage and returns its path. */
function openingCsv(array $rows): string
{
    $path = storage_path('framework/testing/opening-'.uniqid().'.csv');
    @mkdir(dirname($path), 0777, true);
    $f = fopen($path, 'w');
    fputcsv($f, array_keys($rows[0]));
    foreach ($rows as $r) {
        fputcsv($f, array_values($r));
    }
    fclose($f);

    return $path;
}

function importAndPost(string $type, array $rows, array $controls = []): OpeningBatch
{
    $batch = app(Openings::class)->import($type, openingCsv($rows), "{$type}.csv", '2026-10-31');
    expect($batch->error_count)->toBe(0, json_encode($batch->rows()->whereNotNull('errors')->pluck('errors')));
    if ($controls) {
        app(Openings::class)->setControlTotals($batch, $controls);
    }

    return app(Openings::class)->post(app(DocumentWorkflow::class)->approve($batch));
}

it('loads cash, bank, stock and party balances against Opening Balance by currency', function () {
    importAndPost('cash_banks', [['account' => 'Cash Book', 'currency' => 'PKR', 'amount' => '150000'], ['account' => 'Meezan USD', 'currency' => 'USD', 'amount' => '2500']]);
    importAndPost('stock', [
        ['sku' => $this->leather->sku, 'stage' => '', 'status' => 'available', 'location' => '', 'lot' => 'OLD-77', 'quantity' => '40', 'unit_cost' => '500', 'note' => 'count'],
        ['sku' => $this->glove->sku, 'stage' => 'Stitching', 'status' => 'available', 'location' => '', 'lot' => '', 'quantity' => '100', 'unit_cost' => '150', 'note' => ''],
    ]);
    importAndPost('customer_balances', [['customer_code' => 'CUS-0100', 'side' => 'debit', 'amount' => '1200', 'reference' => 'INV 9', 'currency' => 'USD']]);
    importAndPost('vendor_balances', [['vendor_code' => 'VEN-0100', 'side' => 'credit', 'amount' => '30000', 'reference' => '']]);
    importAndPost('contractor_balances', [['contractor_code' => 'CON-0100', 'side' => 'debit', 'amount' => '5000', 'reference' => 'advance']]);
    importAndPost('employee_balances', [['employee_code' => 'EMP-0100', 'side' => 'payable', 'amount' => '8000', 'reference' => '']]);

    expect($this->ledgers->moneyBalance(MoneyAccount::cashBook()))->toBe('150000.00')
        ->and($this->ledgers->moneyBalance($this->usdBank))->toBe('2500.00')
        ->and($this->stock->onHand($this->leather->id))->toBe('40.0000')
        ->and($this->stock->onHand($this->glove->id, $this->stage->id))->toBe('100.0000')
        ->and(Lot::query()->where('lot_number', 'OLD-77')->value('origin'))->toBe('opening')
        ->and($this->ledgers->accountBalance(GlAccount::system('inventory')))->toBe('35000.00')
        ->and($this->ledgers->partyBalance($this->customer))->toBe('1200.00')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('-30000.00')
        ->and($this->ledgers->partyBalance($this->contractor))->toBe('5000.00')
        ->and($this->ledgers->partyBalance($this->employee))->toBe('-8000.00');

    // Opening Balance absorbs the other side, per currency: PKR 150,000 + 35,000 + 5,000 − 30,000 − 8,000; USD 2,500 + 1,200.
    expect($this->ledgers->accountBalance(GlAccount::system('opening_balance'), 'PKR'))->toBe('-152000.00')
        ->and($this->ledgers->accountBalance(GlAccount::system('opening_balance'), 'USD'))->toBe('-3700.00');
});

it('shows row errors in the preview and does not post a batch with errors', function () {
    $batch = $this->openings->import('customer_balances', openingCsv([
        ['customer_code' => 'CUS-0100', 'side' => 'debit', 'amount' => '100', 'reference' => '', 'currency' => 'PKR'],
        ['customer_code' => 'NOPE', 'side' => 'sideways', 'amount' => '-5', 'reference' => '', 'currency' => 'USD'],
        ['customer_code' => 'CUS-0100', 'side' => 'debit', 'amount' => '100', 'reference' => '', 'currency' => 'USD'],
    ]), 'bad.csv', '2026-10-31');

    expect($batch->error_count)->toBe(3);
    $errors = $batch->rows()->pluck('errors')->map(fn ($e) => implode(' ', $e ?? []))->all();
    expect($errors[0])->toContain('currency is USD')
        ->and($errors[1])->toContain('Unknown code')->toContain('Side must be')->toContain('above zero')
        ->and($errors[2])->toContain('twice');
    expect(fn () => $this->openings->post($this->openings->post(app(DocumentWorkflow::class)->approve($batch))))->toThrow(DocumentException::class, 'errors');
    expect($this->ledgers->partyBalance($this->customer))->toBe('0.00');

    expect(fn () => $this->openings->import('stock', openingCsv([['sku' => 'x']]), 'x.csv', '2026-10-31'))->toThrow(DocumentException::class, 'Missing columns');
});

it('prevents importing the same file twice', function () {
    $path = openingCsv([['account' => 'Cash Book', 'currency' => 'PKR', 'amount' => '1000']]);
    $first = $this->openings->import('cash_banks', $path, 'cash.csv', '2026-10-31');
    expect(fn () => $this->openings->import('cash_banks', $path, 'cash-again.csv', '2026-10-31'))->toThrow(DocumentException::class, $first->number);

    app(DocumentWorkflow::class)->cancel($first, 'Wrong date');
    expect($this->openings->import('cash_banks', $path, 'cash.csv', '2026-10-31')->status)->toBe('draft');
});

it('loads loans and advances that payroll then recovers', function () {
    importAndPost('employee_loans', [['employee_code' => 'EMP-0100', 'remaining' => '25000', 'installment' => '10000', 'start_month' => '2026-11']]);
    importAndPost('employee_advances', [['employee_code' => 'EMP-0100', 'remaining' => '5000', 'salary_month' => '2026-11']]);

    expect(EmployeeLoan::query()->sole()->status)->toBe('posted')
        ->and($this->ledgers->partyBalance($this->employee))->toBe('30000.00');
    $line = app(Payrolls::class)->create(Carbon::parse('2026-11-01'))->lines()->sole();
    expect($line->loan_installment)->toBe('10000.00')->and($line->advance_deduction)->toBe('5000.00')->and($line->net_payable)->toBe('25000.00');
});

it('loads open sales orders, open POs, running PTCs and contractor stock', function () {
    importAndPost('open_sales_orders', [
        ['order_ref' => 'OLD-1', 'customer_code' => 'CUS-0100', 'sku' => $this->glove->sku, 'remaining_qty' => '300', 'price' => '6', 'due_date' => '2026-12-01', 'customer_po' => 'HS-1'],
    ], ['USD' => '1800']);
    $order = SalesOrder::query()->sole();
    expect($order->status)->toBe('approved')->and($order->currency_code)->toBe('USD')->and($order->total)->toBe('1800.00');

    importAndPost('open_pos', [['po_ref' => 'P-9', 'vendor_code' => 'VEN-0100', 'sku' => $this->leather->sku, 'remaining_qty' => '50', 'rate' => '520', 'expected_date' => '']]);
    expect(PurchaseOrder::query()->sole()->status)->toBe('approved');

    importAndPost('running_ptcs', [['ptc_ref' => 'J-1', 'sku' => $this->glove->sku, 'current_stage' => 'Stitching', 'quantity' => '80', 'unit_cost' => '200',
        'order_number' => $order->number, 'material_sku' => $this->leather->sku, 'material_qty' => '3', 'material_cost' => '500']]);
    $ptc = Ptc::query()->sole();
    expect($ptc->sales_order_line_id)->toBe($order->lines()->value('id'))
        ->and($ptc->stages()->count())->toBe(1)
        ->and($this->stock->onHand($this->glove->id, $this->stage->id, StockStatus::AVAILABLE, null, $ptc->output_lot_id))->toBe('80.0000');
    // The running PTC continues normally: release reserves to the order at the carried cost.
    app(PtcTransactions::class)->release($ptc, '80', Carbon::parse('2026-11-02'));
    expect($ptc->fresh()->outputLot->unit_cost)->toBe('218.7500');

    importAndPost('contractor_stock', [['contractor_code' => 'CON-0100', 'sku' => $this->leather->sku, 'stage' => '', 'quantity' => '12', 'lot' => '', 'unit_cost' => '500']]);
    expect($this->stock->onHand($this->leather->id, null, StockStatus::IN_PRODUCTION, Location::forContractor($this->contractor)->id))->toBe('12.0000');

    expect($this->openings->import('open_sales_orders', openingCsv([
        ['order_ref' => 'OLD-1', 'customer_code' => 'CUS-0100', 'sku' => $this->glove->sku, 'remaining_qty' => '1', 'price' => '6', 'due_date' => '', 'customer_po' => ''],
    ]), 'again.csv', '2026-10-31')->error_count)->toBe(1);
});

it('compares imported totals with the old system in the cutover report', function () {
    importAndPost('cash_banks', [['account' => 'Cash Book', 'currency' => 'PKR', 'amount' => '1000']], ['PKR' => '1000']);
    importAndPost('vendor_balances', [['vendor_code' => 'VEN-0100', 'side' => 'credit', 'amount' => '500', 'reference' => '']], ['PKR' => '-450']);

    $rows = app(Reports::class)->find('cutover')->rows([]);
    expect($rows->pluck('check')->all())->toBe(['Matches', 'DIFFERENT'])->and($rows[1]['difference'])->toBe('-50.00');
});

it('locks go-live: no more opening documents, period locked to the opening date', function () {
    importAndPost('cash_banks', [['account' => 'Cash Book', 'currency' => 'PKR', 'amount' => '1000']]);
    $draft = $this->openings->import('vendor_balances', openingCsv([['vendor_code' => 'VEN-0100', 'side' => 'credit', 'amount' => '5', 'reference' => '']]), 'v.csv', '2026-10-31');

    expect(fn () => $this->openings->lockGoLive('Live'))->toThrow(DocumentException::class, 'open opening batches');
    $draft->delete();
    expect(fn () => $this->openings->lockGoLive(''))->toThrow(DocumentException::class, 'reason');
    $this->openings->lockGoLive('Cutover signed off');

    expect(app(Settings::class)->get('period_lock.date'))->toBe('2026-10-31')
        ->and(fn () => $this->openings->import('cash_banks', openingCsv([['account' => 'Cash Book', 'currency' => 'PKR', 'amount' => '7']]), 'late.csv', '2026-10-31'))
        ->toThrow(DocumentException::class, 'locked');
});

it('imports from the screen, previews, downloads templates and protects access', function () {
    $this->get(route('opening.template', 'stock'))->assertOk()->assertDownload('opening-stock-template.csv');
    $file = UploadedFile::fake()->createWithContent('cash.csv', "account,currency,amount\nCash Book,PKR,5000\n");
    Livewire::test(Screens\Index::class)->set('type', 'cash_banks')->set('file', $file)->call('upload')->assertHasNoErrors()->assertRedirect();
    $batch = OpeningBatch::query()->sole();
    $this->get(route('opening.show', $batch))->assertOk()->assertSee('Cash Book')->assertSee('OK');
    Livewire::test(Screens\Show::class, ['batch' => $batch])->call('approveDocument')->call('postDocumentAction');
    expect($batch->fresh()->isPosted())->toBeTrue();

    $viewer = userWith(['opening.view']);
    $this->actingAs(userWith([]))->get(route('opening.index'))->assertForbidden();
    Livewire::actingAs($viewer)->test(Screens\Index::class)->call('upload')->assertForbidden();
    Livewire::actingAs($viewer)->test(Screens\Index::class)->call('lock')->assertForbidden();
    Livewire::actingAs(admin())->test(Screens\Index::class)->call('upload')->assertHasErrors(['file' => 'required']);
});
