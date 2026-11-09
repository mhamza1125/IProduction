<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Unit;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Actions\AllocateAdvance;
use App\Modules\Finance\Actions\PostPayment;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Actions\PostGoodsReceipt;
use App\Modules\Purchasing\Actions\PostPurchaseReturn;
use App\Modules\Purchasing\Actions\PostVendorBill;
use App\Modules\Purchasing\Livewire as Screens;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\VendorBill;
use App\Modules\Purchasing\Services\PurchaseOrders;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->vendor = Vendor::factory()->create(['name' => 'Sialkot Leather Co']);
    $this->item = Item::factory()->rawMaterial('pcs')->create(['code' => 'GLOVE-RAW']);
    $this->variant = $this->item->variants()->first();
    $this->stock = app(StockLedger::class);
    $this->ledgers = app(Ledgers::class);
});

function approvedPo(string $qty = '100', string $rate = '20', ?int $unitId = null): PurchaseOrder
{
    $po = app(PurchaseOrders::class)->saveDraft(null, ['vendor_id' => test()->vendor->id, 'order_date' => '2026-11-01'], [[
        'item_variant_id' => test()->variant->id, 'stage_id' => null, 'description' => '', 'quantity' => $qty,
        'unit_id' => $unitId ?? Unit::where('symbol', 'pcs')->value('id'), 'rate' => $rate, 'expected_date' => null,
    ]]);

    return app(DocumentWorkflow::class)->approve($po);
}

function receive(PurchaseOrder $po, string $received, string $accepted, string $rejected = '0', array $extra = []): GoodsReceipt
{
    $line = $po->lines()->first();
    $grn = DB::transaction(function () use ($po, $line, $received, $accepted, $rejected, $extra) {
        $grn = GoodsReceipt::create(['number' => app(DocumentNumber::class)->next('goods_receipt'), 'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id, 'receipt_date' => '2026-11-05']);
        $grn->lines()->create($extra + [
            'purchase_order_line_id' => $line->id, 'item_variant_id' => $line->item_variant_id, 'ordered_qty' => $line->quantity,
            'previously_accepted_qty' => $line->accepted_qty, 'received_qty' => $received, 'accepted_qty' => $accepted, 'rejected_qty' => $rejected,
            'rate' => $line->rate, 'location_id' => Location::mainStore()->id,
        ]);

        return $grn;
    });
    app(DocumentWorkflow::class)->approve($grn);

    return app(PostGoodsReceipt::class)($grn);
}

it('changes nothing when a purchase order is approved: only receipts move stock and the vendor balance', function () {
    $po = approvedPo();

    expect($po->status)->toBe('approved')
        ->and($this->stock->onHand($this->variant->id))->toBe('0.0000')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('0.00');

    expect(fn () => $po->lines()->first()->update(['quantity' => '5']))->toThrow(DocumentException::class);
});

it('receives part of an order: lot in, Dr Inventory / Cr Accounts Payable', function () {
    $po = approvedPo();
    $grn = receive($po, '60', '60');

    $line = $grn->lines()->first();
    expect($line->lot->origin)->toBe('purchased')->and($line->lot->vendor_id)->toBe($this->vendor->id)
        ->and($line->unit_cost)->toBe('20.0000')
        ->and($this->stock->onHand($this->variant->id))->toBe('60.0000')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('-1200.00')
        ->and($po->fresh()->status)->toBe('partly_received')
        ->and($po->lines()->first()->outstanding())->toBe('40.0000');

    receive($po, '40', '40');
    expect($po->fresh()->status)->toBe('received');
});

it('posts a receipt with several lines without lazy loading (strict mode is on outside production)', function () {
    $second = Item::factory()->rawMaterial('pcs')->create(['code' => 'THREAD'])->variants()->first();
    $pcs = Unit::where('symbol', 'pcs')->value('id');
    $po = app(DocumentWorkflow::class)->approve(app(PurchaseOrders::class)->saveDraft(null, ['vendor_id' => $this->vendor->id, 'order_date' => '2026-11-01'], [
        ['item_variant_id' => $this->variant->id, 'stage_id' => null, 'description' => '', 'quantity' => '10', 'unit_id' => $pcs, 'rate' => '20', 'expected_date' => null],
        ['item_variant_id' => $second->id, 'stage_id' => null, 'description' => '', 'quantity' => '5', 'unit_id' => $pcs, 'rate' => '8', 'expected_date' => null],
    ]));

    $grn = GoodsReceipt::create(['number' => app(DocumentNumber::class)->next('goods_receipt'), 'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id, 'receipt_date' => '2026-11-05']);
    foreach ($po->lines()->orderBy('id')->get() as $line) {
        $grn->lines()->create([
            'purchase_order_line_id' => $line->id, 'item_variant_id' => $line->item_variant_id, 'ordered_qty' => $line->quantity, 'previously_accepted_qty' => '0',
            'received_qty' => $line->quantity, 'accepted_qty' => $line->quantity, 'rejected_qty' => '0', 'rate' => $line->rate, 'location_id' => Location::mainStore()->id,
        ]);
    }
    app(PostGoodsReceipt::class)(app(DocumentWorkflow::class)->approve($grn));

    expect($this->stock->onHand($this->variant->id))->toBe('10.0000')
        ->and($this->stock->onHand($second->id))->toBe('5.0000');
});

it('requires received = accepted + rejected', function () {
    $po = approvedPo();
    receive($po, '10', '9', '0');
})->throws(DocumentException::class, 'received must equal accepted + rejected');

it('handles rejected goods: returned ones never enter stock, kept ones go to Rejected and are paid', function () {
    $po = approvedPo();
    receive($po, '50', '45', '5', ['rejected_action' => 'return', 'reject_reason_id' => Lookup::where('type', 'rejection_reason')->value('id')]);

    expect($this->stock->onHand($this->variant->id))->toBe('45.0000')
        ->and($this->stock->onHand($this->variant->id, status: StockStatus::REJECTED))->toBe('0.0000')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('-900.00');

    receive($po, '10', '7', '3', ['rejected_action' => 'keep']);

    expect($this->stock->onHand($this->variant->id, status: StockStatus::REJECTED))->toBe('3.0000')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('-1100.00');
});

it('handles 105 received against an order of 100 with each option', function (?string $action, string $stock, string $payable, string $unitCost) {
    $po = approvedPo('100', '20');
    $grn = receive($po, '105', '105', '0', ['over_receipt_action' => $action]);

    $line = $grn->lines()->first();
    expect($this->stock->onHand($this->variant->id))->toBe($stock)
        ->and($line->payable_qty)->toBe($payable)
        ->and($line->unit_cost)->toBe($unitCost)
        ->and($line->extra_qty)->toBe('5.0000')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('-'.number_format((float) $payable * 20, 2, '.', ''))
        ->and($po->fresh()->status)->toBe('received');
})->with([
    'accept and pay' => ['accept_pay', '105.0000', '105.0000', '20.0000'],
    'accept as free' => ['accept_free', '105.0000', '100.0000', '19.0476'],  // 2,000 ÷ 105
    'return extra' => ['return_extra', '100.0000', '100.0000', '20.0000'],
]);

it('requires a decision and the override permission above the tolerance', function () {
    $po = approvedPo();
    expect(fn () => receive($po, '105', '105'))->toThrow(DocumentException::class, 'above the tolerance');

    $this->actingAs(userWith(['goods_receipts.approve', 'goods_receipts.post']));
    expect(fn () => receive($po, '105', '105', '0', ['over_receipt_action' => 'accept_pay']))->toThrow(DocumentException::class, 'authorised user');
});

it('accepts extra within the tolerance without a decision (item, then vendor, then company)', function () {
    $this->vendor->update(['over_receipt_percent' => '5']);
    receive(approvedPo(), '105', '105');
    expect($this->stock->onHand($this->variant->id))->toBe('105.0000');

    $this->item->update(['over_receipt_percent' => '0']);  // the item's own setting wins
    expect(fn () => receive(approvedPo(), '101', '101'))->toThrow(DocumentException::class, 'above the tolerance');
});

it('converts order units to stock units: 10 dozen at 240 = 120 pieces at 20', function () {
    receive(approvedPo('10', '240', Unit::where('symbol', 'dozen')->value('id')), '10', '10');

    expect($this->stock->onHand($this->variant->id))->toBe('120.0000')
        ->and(Lot::first()->unit_cost)->toBe('20.0000')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('-2400.00');
});

it('bills received goods, posts only extras, and blocks billing more than received', function () {
    $grn = receive(approvedPo(), '100', '100');
    $line = $grn->lines()->first();

    $bill = DB::transaction(fn () => tap(VendorBill::create([
        'number' => 'BILL-26-0001', 'vendor_id' => $this->vendor->id, 'vendor_invoice_number' => 'INV-77', 'bill_date' => '2026-11-06',
        'goods_amount' => '2000', 'tax_amount' => '360', 'charges_amount' => '100', 'deductions_amount' => '60', 'total' => '2400',
    ]), fn ($b) => $b->lines()->create(['goods_receipt_line_id' => $line->id, 'item_variant_id' => $this->variant->id, 'quantity' => '100', 'rate' => '20', 'amount' => '2000'])));
    app(DocumentWorkflow::class)->approve($bill);
    app(PostVendorBill::class)($bill);

    // 2,000 from the receipt + 400 extras = 2,400 owed.
    expect($this->ledgers->partyBalance($this->vendor))->toBe('-2400.00')
        ->and($line->fresh()->billed_qty)->toBe('100.0000');

    $this->actingAs(userWith(['vendor_bills.view', 'vendor_bills.create']));
    Livewire::test(Screens\Bills\Form::class)->set('vendor_id', $this->vendor->id)->set('vendor_invoice_number', 'INV-78')
        ->set('quantities', [$line->id => '1'])->call('save')->assertHasErrors('quantities.'.$line->id);
});

it('pays a bill from cash, keeps the rest as an advance, and applies the advance later', function () {
    $grn = receive(approvedPo('100', '20'), '100', '100');
    $line = $grn->lines()->first();
    $bill = DB::transaction(fn () => tap(VendorBill::create(['number' => 'BILL-1', 'vendor_id' => $this->vendor->id, 'vendor_invoice_number' => 'V1', 'bill_date' => '2026-11-06', 'goods_amount' => '2000', 'total' => '2000']),
        fn ($b) => $b->lines()->create(['goods_receipt_line_id' => $line->id, 'item_variant_id' => $this->variant->id, 'quantity' => '100', 'rate' => '20', 'amount' => '2000'])));
    app(DocumentWorkflow::class)->approve($bill);
    app(PostVendorBill::class)($bill);

    $cash = MoneyAccount::cashBook();
    DB::transaction(fn () => app(JournalPoster::class)->post((new JournalEntry(Carbon::parse('2026-11-01'), 'PKR', 'Capital'))->debit($cash, '5000')->credit('owner_capital', '5000')));

    $payment = DB::transaction(fn () => Payment::create([
        'number' => 'PAY-26-0001', 'payment_date' => '2026-11-07', 'payee_kind' => 'vendor', 'payee_type' => $this->vendor->getMorphClass(), 'payee_id' => $this->vendor->id,
        'money_account_id' => $cash->id, 'amount' => '2500', 'planned_allocations' => [['type' => $bill->getMorphClass(), 'id' => $bill->id, 'amount' => '1500']],
    ]));
    app(DocumentWorkflow::class)->approve($payment);
    app(PostPayment::class)($payment);

    expect($bill->fresh())->paid_amount->toBe('1500.00')->status->toBe('partly_paid')
        ->and($payment->fresh()->unallocated())->toBe('1000.00')
        ->and($this->ledgers->moneyBalance($cash))->toBe('2500.00')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('500.00'); // AP −500, advance +1,000

    app(AllocateAdvance::class)($payment->fresh(), $bill->fresh(), '500', now());

    expect($bill->fresh())->status->toBe('paid')
        ->and($payment->fresh()->unallocated())->toBe('500.00')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('500.00');
});

it('does not let a payment exceed the cash balance', function () {
    $payment = DB::transaction(fn () => Payment::create([
        'number' => 'PAY-X', 'payment_date' => '2026-11-07', 'payee_kind' => 'vendor', 'payee_type' => $this->vendor->getMorphClass(), 'payee_id' => $this->vendor->id,
        'money_account_id' => MoneyAccount::cashBook()->id, 'amount' => '10',
    ]));
    app(DocumentWorkflow::class)->approve($payment);
    app(PostPayment::class)($payment);
})->throws(DocumentException::class, 'cannot pay');

it('returns goods from a lot, reducing stock and the vendor balance, and reopens the order for a replacement', function (bool $replacement) {
    $po = approvedPo('100', '20');
    $grn = receive($po, '100', '100');
    $line = $grn->lines()->first();

    $return = DB::transaction(fn () => tap(PurchaseReturn::create([
        'number' => 'PR-1', 'vendor_id' => $this->vendor->id, 'goods_receipt_id' => $grn->id, 'return_date' => '2026-11-08',
        'reason' => 'Torn', 'replacement_required' => $replacement, 'total' => '200',
    ]), fn ($r) => $r->lines()->create([
        'goods_receipt_line_id' => $line->id, 'item_variant_id' => $this->variant->id, 'lot_id' => $line->lot_id, 'status' => 'available',
        'location_id' => Location::mainStore()->id, 'quantity' => '10', 'rate' => '20', 'amount' => '200',
    ])));
    app(DocumentWorkflow::class)->approve($return);
    app(PostPurchaseReturn::class)($return);

    expect($this->stock->onHand($this->variant->id))->toBe('90.0000')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('-1800.00')
        ->and($po->lines()->first()->returned_qty)->toBe('10.0000')
        ->and($po->fresh()->status)->toBe($replacement ? 'partly_received' : 'received')
        ->and($po->lines()->first()->outstanding())->toBe($replacement ? '10.0000' : '0.0000');
})->with(['no replacement' => false, 'replacement required' => true]);

it('closes an order short or cancels it only when nothing was received', function () {
    $po = approvedPo();
    receive($po, '30', '30');

    expect(fn () => app(PurchaseOrders::class)->cancel($po, 'Not needed'))->toThrow(DocumentException::class, 'Close the remaining quantity short');

    app(PurchaseOrders::class)->closeShort($po, 'Vendor out of stock');
    expect($po->fresh()->status)->toBe('closed_short')->and($po->lines()->first())->closed_short_qty->toBe('70.0000');

    $other = approvedPo();
    app(PurchaseOrders::class)->markSent($other);
    app(PurchaseOrders::class)->cancel($other, 'Duplicate');
    expect($other->fresh()->status)->toBe('cancelled');
});

it('creates orders and receipts through the screens', function () {
    Livewire::test(Screens\Orders\Form::class)
        ->set('vendor_id', $this->vendor->id)
        ->set('lines.0.item_variant_id', $this->variant->id)->set('lines.0.quantity', '50')->set('lines.0.rate', '12.5')
        ->call('save')->assertHasNoErrors();

    $po = PurchaseOrder::firstOrFail();
    expect($po->number)->toBe('PO-26-0001')->and($po->total)->toBe('625.00');

    app(DocumentWorkflow::class)->approve($po);
    $line = $po->lines()->first();

    $this->get(route('purchasing.receipts.create', ['order' => $po->id]))->assertOk()->assertSee('Receive goods');

    Livewire::withQueryParams(['order' => $po->id])->test(Screens\Receipts\Form::class)
        ->set("lines.{$line->id}.received_qty", '50')->set("lines.{$line->id}.rejected_qty", '2')
        ->call('save')->assertHasErrors("lines.{$line->id}.reject_reason_id")
        ->set("lines.{$line->id}.reject_reason_id", Lookup::where('type', 'rejection_reason')->value('id'))
        ->call('save')->assertHasNoErrors();

    $grn = GoodsReceipt::firstOrFail();
    expect($grn->lines()->first())->accepted_qty->toBe('48.0000')->rejected_qty->toBe('2.0000');
});

it('enforces purchasing permissions and renders the pages', function () {
    $po = approvedPo();
    $grn = receive($po, '10', '10');

    foreach ([route('purchasing.orders.index'), route('purchasing.orders.show', $po), route('purchasing.orders.print', $po), route('purchasing.receipts.index'),
        route('purchasing.receipts.show', $grn), route('purchasing.receipts.print', $grn), route('purchasing.bills.index'), route('purchasing.bills.create', ['receipt' => $grn->id]),
        route('purchasing.returns.index'), route('purchasing.returns.create', ['receipt' => $grn->id]), route('finance.payments.index'), route('finance.payments.create'),
        route('people.vendors.show', $this->vendor)] as $url) {
        $this->get($url)->assertOk();
    }

    $this->actingAs(userWith(['purchase_orders.view']));
    $this->get(route('purchasing.orders.create'))->assertForbidden();
    $this->get(route('purchasing.receipts.index'))->assertForbidden();
    Livewire::test(Screens\Orders\Show::class, ['order' => $po])->call('closeShort')->assertForbidden();
});
