<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Unit;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Actions\PostPayment;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Actions\PostGoodsReceipt;
use App\Modules\Purchasing\Livewire\LandedCosts as Screens;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\LandedCost;
use App\Modules\Purchasing\Services\LandedCosts;
use App\Modules\Purchasing\Services\PurchaseOrders;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Fixtures\TestDocument;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->vendor = Vendor::factory()->create(['name' => 'Leather Co']);
    $this->transporter = Vendor::factory()->create(['name' => 'Fast Cargo']);
    $this->leather = Item::factory()->rawMaterial('pcs')->create(['code' => 'LEA'])->variants()->first();
    $this->thread = Item::factory()->rawMaterial('pcs')->create(['code' => 'THR'])->variants()->first();
    $this->ledgers = app(Ledgers::class);
});

/** Receives 100 leather at 20 and 50 thread at 40 on one receipt (value 2,000 + 2,000). */
function receivedGoods(): GoodsReceipt
{
    $po = app(PurchaseOrders::class)->saveDraft(null, ['vendor_id' => test()->vendor->id, 'order_date' => '2026-11-01'], [
        ['item_variant_id' => test()->leather->id, 'stage_id' => null, 'description' => '', 'quantity' => '100', 'unit_id' => Unit::where('symbol', 'pcs')->value('id'), 'rate' => '20', 'expected_date' => null],
        ['item_variant_id' => test()->thread->id, 'stage_id' => null, 'description' => '', 'quantity' => '50', 'unit_id' => Unit::where('symbol', 'pcs')->value('id'), 'rate' => '40', 'expected_date' => null],
    ]);
    $po = approver(DocumentWorkflow::class)->approve($po);
    $grn = DB::transaction(function () use ($po) {
        $grn = GoodsReceipt::create(['number' => app(DocumentNumber::class)->next('goods_receipt'), 'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id, 'receipt_date' => '2026-11-05']);
        foreach ($po->lines as $line) {
            $grn->lines()->create(['purchase_order_line_id' => $line->id, 'item_variant_id' => $line->item_variant_id, 'ordered_qty' => $line->quantity,
                'previously_accepted_qty' => '0', 'received_qty' => $line->quantity, 'accepted_qty' => $line->quantity, 'rejected_qty' => '0',
                'rate' => $line->rate, 'location_id' => Location::mainStore()->id]);
        }

        return $grn;
    });

    return app(PostGoodsReceipt::class)(approver(DocumentWorkflow::class)->approve($grn));
}

function landed(GoodsReceipt $grn, array $charges, string $method = 'value'): LandedCost
{
    $doc = app(LandedCosts::class)->save(null, ['cost_date' => '2026-11-06', 'vendor_id' => test()->transporter->id, 'method' => $method], $charges, [$grn->id]);

    return app(LandedCosts::class)->post(approver(DocumentWorkflow::class)->approve($doc));
}

it('adds freight to the cost of the goods, by value, payable to the transporter', function () {
    $grn = receivedGoods();
    $doc = landed($grn, [['charge_type' => 'freight', 'amount' => '300'], ['charge_type' => 'loading', 'amount' => '100']]);

    $lots = Lot::query()->where('origin', 'purchased')->orderBy('id')->get();
    // 400 split 200/200 by value: leather 200 ÷ 100 = +2, thread 200 ÷ 50 = +4.
    expect($lots[0]->unit_cost)->toBe('22.0000')->and($lots[1]->unit_cost)->toBe('44.0000')
        ->and($doc->allocations()->sum('to_stock'))->toEqual(400)
        ->and($this->ledgers->accountBalance(GlAccount::system('inventory')))->toBe('4400.00')
        ->and($this->ledgers->partyBalance($this->transporter))->toBe('-400.00')
        ->and($this->ledgers->partyBalance($this->vendor))->toBe('-4000.00');

    // It is payable: a payment to the transporter settles it.
    $cash = MoneyAccount::cashBook();
    DB::transaction(fn () => app(JournalPoster::class)->post((new JournalEntry(Carbon::parse('2026-11-01'), 'PKR', 'Capital'))->debit($cash, '1000')->credit('owner_capital', '1000')));
    $payment = DB::transaction(fn () => Payment::create([
        'number' => 'PAY-T', 'payment_date' => '2026-11-07', 'payee_kind' => 'vendor', 'payee_type' => $this->transporter->getMorphClass(), 'payee_id' => $this->transporter->id,
        'money_account_id' => $cash->id, 'amount' => '400', 'planned_allocations' => [['type' => $doc->getMorphClass(), 'id' => $doc->id, 'amount' => '400']],
    ]));
    app(PostPayment::class)(approver(DocumentWorkflow::class)->approve($payment));
    expect($doc->fresh()->paid_amount)->toBe('400.00')->and($this->ledgers->partyBalance($this->transporter))->toBe('0.00');
});

it('spreads by quantity, and sends the part for goods already used to cost of goods sold', function () {
    $grn = receivedGoods();
    // 60 of the 100 leather already used before the charge arrived.
    DB::transaction(fn () => app(StockLedger::class)->remove(new StockBucket($this->leather->id, null, StockStatus::AVAILABLE, Location::mainStore()->id, Lot::query()->where('item_variant_id', $this->leather->id)->value('id')),
        '60', new StockSource(TestDocument::create(['number' => 'TD-1', 'document_date' => '2026-11-05']), Carbon::parse('2026-11-05'))));

    $doc = landed($grn, [['charge_type' => 'customs_duty', 'amount' => '150']], 'quantity');

    // 150 over 150 units = 1 per unit: leather 100 (40 on hand → 40 to stock, 60 to COGS), thread 50 (all on hand).
    $leather = $doc->allocations()->whereHas('lot', fn ($q) => $q->where('item_variant_id', $this->leather->id))->sole();
    expect($leather->amount)->toBe('100.00')->and($leather->to_stock)->toBe('40.00')->and($leather->to_cogs)->toBe('60.00')
        ->and($leather->unit_cost_after)->toBe('21.0000')
        ->and($this->ledgers->accountBalance(GlAccount::system('cogs')))->toBe('60.00');
});

it('validates landed costs and keeps them to posted receipts', function () {
    $service = app(LandedCosts::class);
    $grn = receivedGoods();

    expect(fn () => $service->save(null, ['cost_date' => '2026-11-06', 'vendor_id' => $this->transporter->id, 'method' => 'value'], [], [$grn->id]))->toThrow(DocumentException::class, 'at least one charge')
        ->and(fn () => $service->save(null, ['cost_date' => '2026-11-06', 'method' => 'value'], [['charge_type' => 'freight', 'amount' => '10']], [$grn->id]))->toThrow(DocumentException::class, 'paid from')
        ->and(fn () => $service->save(null, ['cost_date' => '2026-11-06', 'vendor_id' => $this->transporter->id, 'method' => 'value'], [['charge_type' => 'freight', 'amount' => '10']], []))->toThrow(DocumentException::class, 'goods receipts');

    // The creator cannot approve it.
    $doc = $service->save(null, ['cost_date' => '2026-11-06', 'vendor_id' => $this->transporter->id, 'method' => 'value'], [['charge_type' => 'freight', 'amount' => '10']], [$grn->id]);
    expect(fn () => app(DocumentWorkflow::class)->approve($doc))->toThrow(DocumentException::class, 'another user');
});

it('creates, shows and lists landed costs on screen, with permissions', function () {
    $grn = receivedGoods();
    Livewire::test(Screens\Form::class)
        ->set('vendor_id', $this->transporter->id)->set('charges.0.amount', '250')->set('receipt_ids', [$grn->id])
        ->call('save')->assertHasNoErrors()->assertRedirect();
    $doc = LandedCost::query()->sole();
    expect($doc->total)->toBe('250.00')->and($doc->receipts()->pluck('goods_receipts.id')->all())->toBe([$grn->id]);

    $this->get(route('purchasing.landed-costs.index'))->assertOk()->assertSee($doc->number);
    $this->get(route('purchasing.landed-costs.show', $doc))->assertOk()->assertSee($grn->number);
    $this->get(route('purchasing.landed-costs.create'))->assertOk();

    approver(LandedCosts::class)->post(approver(DocumentWorkflow::class)->approve($doc));
    $this->get(route('purchasing.landed-costs.show', $doc))->assertOk()->assertSee('How the charges were added');

    $this->actingAs(userWith(['goods_receipts.view']));
    $this->get(route('purchasing.landed-costs.index'))->assertForbidden();
});
