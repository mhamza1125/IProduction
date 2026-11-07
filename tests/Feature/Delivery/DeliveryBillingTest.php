<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\CreditNotes;
use App\Modules\Delivery\Services\Deliveries;
use App\Modules\Delivery\Services\DeliveryReturns;
use App\Modules\Delivery\Services\Invoices;
use App\Modules\Delivery\Services\Packing;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Customer;
use App\Modules\Production\Models\Rejection;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TestDocument;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->customer = Customer::factory()->create(['name' => 'Hamburg Sports', 'currency_code' => 'USD']);
    $this->glove = Item::factory()->finished()->create(['code' => 'GLV'])->variants()->first();
    $this->ball = Item::factory()->finished()->create(['code' => 'BALL'])->variants()->first();
    $this->deliveries = app(Deliveries::class);
    $this->packing = app(Packing::class);
    $this->invoices = app(Invoices::class);
    $this->stock = app(StockLedger::class);
    $this->day = Carbon::parse('2026-11-10');
});

function dbStock(ItemVariant $variant, string $qty, string $cost, string $status = StockStatus::AVAILABLE): Lot
{
    return DB::transaction(function () use ($variant, $qty, $cost, $status) {
        $doc = TestDocument::create(['number' => 'TD-'.uniqid(), 'document_date' => '2026-11-01']);
        $lot = app(StockLedger::class)->createLot($variant->id, 'purchased', $cost, Carbon::parse('2026-11-01'));
        app(StockLedger::class)->receive(new StockBucket($variant->id, null, $status, Location::mainStore()->id, $lot->id), $qty, new StockSource($doc, Carbon::parse('2026-11-01')));

        return $lot;
    });
}

function dbOrder(?Customer $customer = null, array $lines = [], array $extra = []): SalesOrder
{
    $customer ??= test()->customer;
    $lines = $lines ?: [[test()->glove, '100', '5']];
    $order = app(SalesOrders::class)->save(null, ['customer_id' => $customer->id, 'order_date' => '2026-11-01'] + $extra, array_map(fn ($l) => [
        'item_variant_id' => $l[0]->id, 'description' => '', 'quantity' => $l[1], 'unit_price' => $l[2], 'tax_rate' => '', 'due_date' => null,
    ], $lines));

    return app(DocumentWorkflow::class)->approve($order);
}

/** Saves, allocates, packs (one carton per line) and dispatches. */
function dispatchLines(array $lines, ?Customer $customer = null): Delivery
{
    $d = app(Deliveries::class)->save(null, ['customer_id' => ($customer ?? test()->customer)->id, 'delivery_date' => '2026-11-10'], $lines);
    $d = app(Deliveries::class)->allocate($d);
    foreach ($d->lines as $line) {
        app(Packing::class)->addCarton($d, ['gross_weight' => '10'], [$line->id => $line->quantity]);
    }
    app(Packing::class)->complete($d);

    return app(Deliveries::class)->dispatch($d->fresh());
}

it('combines lines from three sales orders of one customer into one delivery', function () {
    dbStock($this->glove, '300', '2');
    $orders = collect([1, 2, 3])->map(fn () => dbOrder());

    $delivery = dispatchLines($orders->map(fn ($o) => ['sales_order_line_id' => $o->lines()->first()->id, 'quantity' => '100'])->all());

    expect($delivery->status)->toBe('dispatched')
        ->and($delivery->lines)->toHaveCount(3)
        ->and($delivery->currency_code)->toBe('USD')
        ->and($this->stock->onHand($this->glove->id))->toBe('0.0000')
        ->and($orders->every(fn ($o) => $o->fresh()->status === 'delivered'))->toBeTrue()
        ->and(SalesOrder::query()->count())->toBe(3);

    // Dr COGS, Cr Inventory at lot cost (300 × 2), in PKR.
    $cogs = JournalLine::query()->with('journal')->whereHas('journal', fn ($q) => $q->where('source_type', $delivery->getMorphClass()))->get();
    expect($cogs->sum('debit'))->toEqual(600)->and($cogs->first()->journal->currency_code)->toBe('PKR');
});

it('refuses lines of different customers or currencies on one delivery', function () {
    $other = Customer::factory()->create(['currency_code' => 'USD']);
    $euro = Customer::factory()->create(['currency_code' => 'EUR']);
    $a = dbOrder();
    $b = dbOrder($other);

    expect(fn () => $this->deliveries->save(null, ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-10'], [
        ['sales_order_line_id' => $a->lines()->first()->id, 'quantity' => '1'], ['sales_order_line_id' => $b->lines()->first()->id, 'quantity' => '1'],
    ]))->toThrow(DocumentException::class, 'another customer');

    // The customer's currency changed between orders: the old order stays in USD.
    $c = dbOrder($euro);
    $euro->update(['currency_code' => 'GBP']);
    $d = dbOrder($euro);
    expect(fn () => $this->deliveries->save(null, ['customer_id' => $euro->id, 'delivery_date' => '2026-11-10'], [
        ['sales_order_line_id' => $c->lines()->first()->id, 'quantity' => '1'], ['sales_order_line_id' => $d->lines()->first()->id, 'quantity' => '1'],
    ]))->toThrow(DocumentException::class, 'one currency');
});

it('splits one order line across two deliveries and blocks over-delivery without approval', function () {
    dbStock($this->glove, '200', '2');
    $line = dbOrder()->lines()->first();

    dispatchLines([['sales_order_line_id' => $line->id, 'quantity' => '60']]);
    expect($line->order->fresh()->status)->toBe('partly_delivered');

    $this->actingAs(userWith(['deliveries.create']));
    expect(fn () => $this->deliveries->save(null, ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-11'], [['sales_order_line_id' => $line->id, 'quantity' => '41']]))
        ->toThrow(DocumentException::class, 'only 40.0000');

    $this->actingAs(admin());
    dispatchLines([['sales_order_line_id' => $line->id, 'quantity' => '40']]);
    expect($line->fresh()->delivered_qty)->toBe('100.0000')->and($line->order->fresh()->status)->toBe('delivered');
});

it('reserves at allocation, releases on cancel, and rolls back the whole delivery on a shortage', function () {
    dbStock($this->glove, '50', '2');
    dbStock($this->ball, '5', '3');
    $order = dbOrder(null, [[$this->glove, '50', '5'], [$this->ball, '10', '7']]);
    [$gl, $bl] = $order->lines()->get()->all();

    $d = $this->deliveries->save(null, ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-10'], [
        ['sales_order_line_id' => $gl->id, 'quantity' => '50'], ['sales_order_line_id' => $bl->id, 'quantity' => '10'],
    ]);
    expect(fn () => $this->deliveries->allocate($d))->toThrow(DocumentException::class, 'short by 5');
    expect($d->fresh()->status)->toBe('draft')->and(Reservation::query()->count())->toBe(0);

    $d = $this->deliveries->save($d->fresh(), ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-10'], [['sales_order_line_id' => $gl->id, 'quantity' => '50']]);
    $this->deliveries->allocate($d);
    expect($this->stock->freeQuantity($this->glove->id))->toBe('0.0000')
        ->and($this->stock->onHand($this->glove->id))->toBe('50.0000');

    $this->deliveries->cancel($d->fresh(), 'Customer postponed');
    expect($this->stock->freeQuantity($this->glove->id))->toBe('50.0000')->and($d->fresh()->status)->toBe('cancelled');
});

it('uses the order reservation first and never another order\'s', function () {
    dbStock($this->glove, '30', '2');
    $mine = dbOrder()->lines()->first();
    $theirs = dbOrder()->lines()->first();
    DB::transaction(fn () => $this->stock->reserve($theirs, $this->glove->id, '25'));

    $d = $this->deliveries->save(null, ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-10'], [['sales_order_line_id' => $mine->id, 'quantity' => '10']]);
    expect(fn () => $this->deliveries->allocate($d))->toThrow(DocumentException::class, 'short by 5');
});

it('requires packing to match the delivery, supports repeat cartons and totals', function () {
    dbStock($this->glove, '100', '2');
    $line = dbOrder()->lines()->first();
    $d = $this->deliveries->allocate($this->deliveries->save(null, ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-10'], [['sales_order_line_id' => $line->id, 'quantity' => '100']]));
    $dl = $d->lines()->first();

    expect(fn () => $this->deliveries->dispatch($d->fresh()))->toThrow(DocumentException::class);
    $this->packing->addCarton($d, ['carton_type' => 'Master', 'gross_weight' => '12.5', 'net_weight' => '11'], [$dl->id => '24'], 4);
    expect(fn () => $this->packing->addCarton($d, [], [$dl->id => '5']))->toThrow(DocumentException::class, 'more');
    expect(fn () => $this->packing->complete($d))->toThrow(DocumentException::class, 'packed 96');
    $this->packing->addCarton($d, ['gross_weight' => '2'], [$dl->id => '4']);
    $this->packing->complete($d);

    expect($this->packing->totals($d->packingList()->first()))->toMatchArray(['cartons' => 5, 'pieces' => '100.0000', 'gross' => '52.000', 'net' => '44.000'])
        ->and($d->packingList->cartons->pluck('carton_number')->all())->toBe([1, 2, 3, 4, 5]);
    expect($this->deliveries->dispatch($d->fresh())->status)->toBe('dispatched');
    expect(fn () => $this->deliveries->dispatch($d->fresh()))->toThrow(DocumentException::class, 'already posted');
});

it('dispatches rejected stock only for an order marked for it', function () {
    dbStock($this->glove, '20', '2', StockStatus::REJECTED);
    $normal = dbOrder()->lines()->first();
    $seconds = dbOrder(null, [], ['sells_rejected_stock' => true])->lines()->first();

    $d = $this->deliveries->save(null, ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-10'], [['sales_order_line_id' => $normal->id, 'quantity' => '5']]);
    expect(fn () => $this->deliveries->allocate($d))->toThrow(DocumentException::class);

    dispatchLines([['sales_order_line_id' => $seconds->id, 'quantity' => '20']]);
    expect($this->stock->onHand($this->glove->id, null, StockStatus::REJECTED))->toBe('0.0000');
});

it('invoices dispatched lines once, in the customer currency, with optional tax and price permission', function () {
    dbStock($this->glove, '100', '2');
    dbStock($this->ball, '50', '3');
    $o1 = dbOrder(null, [[$this->glove, '100', '5']]);
    $o2 = dbOrder(null, [[$this->ball, '50', '8']]);
    $d1 = dispatchLines([['sales_order_line_id' => $o1->lines()->first()->id, 'quantity' => '100']]);
    $d2 = dispatchLines([['sales_order_line_id' => $o2->lines()->first()->id, 'quantity' => '50']]);
    [$l1, $l2] = [$d1->lines()->first(), $d2->lines()->first()];

    $this->actingAs(userWith(['invoices.create']));
    expect(fn () => $this->invoices->save(null, ['customer_id' => $this->customer->id, 'invoice_date' => '2026-11-12'], [['delivery_line_id' => $l1->id, 'unit_price' => '6']]))
        ->toThrow(DocumentException::class, 'permission');

    $this->actingAs(admin());
    // One invoice covers two deliveries. Gloves: no tax (export); balls: 10 discount, 17% tax.
    $invoice = $this->invoices->save(null, ['customer_id' => $this->customer->id, 'invoice_date' => '2026-11-12'], [
        ['delivery_line_id' => $l1->id], ['delivery_line_id' => $l2->id, 'discount' => '10', 'tax_rate' => '17'],
    ]);
    expect($invoice->subtotal)->toBe('900.00')->and($invoice->discount_total)->toBe('10.00')
        ->and($invoice->tax_total)->toBe('66.30')->and($invoice->total)->toBe('956.30')
        ->and($invoice->currency_code)->toBe('USD');

    expect(fn () => $this->invoices->save(null, ['customer_id' => $this->customer->id, 'invoice_date' => '2026-11-12'], [['delivery_line_id' => $l1->id]]))
        ->toThrow(DocumentException::class, 'already invoiced');

    $this->invoices->post(app(DocumentWorkflow::class)->approve($invoice));
    $ledgers = app(Ledgers::class);
    expect($ledgers->partyBalance($this->customer))->toBe('956.30')
        ->and($invoice->fresh()->balance())->toBe('956.30')
        ->and($o1->lines()->first()->invoiced_qty)->toBe('100.0000');
});

it('returns goods to Returned status, reopens the line for a replacement and inspects them', function () {
    $lot = dbStock($this->glove, '100', '2');
    $line = dbOrder()->lines()->first();
    $d = dispatchLines([['sales_order_line_id' => $line->id, 'quantity' => '100']]);
    $returns = app(DeliveryReturns::class);

    expect(fn () => $returns->save(null, ['delivery_line_id' => $d->lines()->first()->id, 'quantity' => '101', 'reason' => 'x', 'return_date' => '2026-11-15']))->toThrow(DocumentException::class);
    $r = $returns->save(null, ['delivery_line_id' => $d->lines()->first()->id, 'quantity' => '10', 'reason' => 'Wrong size', 'return_date' => '2026-11-15', 'replacement_required' => true]);
    $returns->post(app(DocumentWorkflow::class)->approve($r));

    expect($this->stock->onHand($this->glove->id, null, StockStatus::RETURNED))->toBe('10.0000')
        ->and($line->fresh()->remaining())->toBe('10.0000')
        ->and($line->fresh()->returned_qty)->toBe('10.0000')
        ->and($line->order->fresh()->status)->toBe('partly_delivered')
        ->and($r->fresh()->lot_id)->toBe($lot->id)
        ->and($lot->fresh()->origin)->toBe('purchased');

    $returns->restock($r->fresh(), '6', null, $this->day);
    $rejection = $returns->reject($r->fresh(), '4', 'Stitching open', $this->day);
    expect($this->stock->freeQuantity($this->glove->id))->toBe('6.0000')
        ->and($this->stock->onHand($this->glove->id, null, StockStatus::REJECTED))->toBe('4.0000')
        ->and($rejection)->toBeInstanceOf(Rejection::class)
        ->and(fn () => $returns->restock($r->fresh(), '1', null, $this->day))->toThrow(DocumentException::class);
});

it('closes the returned quantity when no replacement is needed', function () {
    dbStock($this->glove, '100', '2');
    $line = dbOrder()->lines()->first();
    $d = dispatchLines([['sales_order_line_id' => $line->id, 'quantity' => '100']]);
    $returns = app(DeliveryReturns::class);
    $returns->post(app(DocumentWorkflow::class)->approve($returns->save(null, ['delivery_line_id' => $d->lines()->first()->id, 'quantity' => '5', 'reason' => 'Damaged', 'return_date' => '2026-11-15'])));

    expect($line->fresh()->remaining())->toBe('0.0000')->and($line->order->fresh()->status)->toBe('delivered');
});

it('posts credit notes in the customer currency against an invoice, keeping the rest as credit', function () {
    dbStock($this->glove, '100', '2');
    $d = dispatchLines([['sales_order_line_id' => dbOrder()->lines()->first()->id, 'quantity' => '100']]);
    $invoice = $this->invoices->post(app(DocumentWorkflow::class)->approve($this->invoices->save(null, ['customer_id' => $this->customer->id, 'invoice_date' => '2026-11-12'], [['delivery_line_id' => $d->lines()->first()->id]])));
    $notes = app(CreditNotes::class);

    expect(fn () => $notes->save(null, ['customer_id' => $this->customer->id, 'credit_date' => '2026-11-20', 'reason' => ''], [['description' => 'x', 'amount' => '1']]))->toThrow(DocumentException::class, 'reason');

    $cn = $notes->save(null, ['customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'credit_date' => '2026-11-20', 'reason' => 'Price correction'], [['description' => 'Price 5 → 4.5', 'amount' => '50']]);
    $notes->post(app(DocumentWorkflow::class)->approve($cn));
    expect($invoice->fresh()->balance())->toBe('450.00')
        ->and(app(Ledgers::class)->partyBalance($this->customer))->toBe('450.00');

    $big = $notes->post(app(DocumentWorkflow::class)->approve($notes->save(null, ['customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'credit_date' => '2026-11-21', 'reason' => 'Settlement'], [['description' => 'Goodwill', 'amount' => '500']])));
    expect($invoice->fresh()->balance())->toBe('0.00')
        ->and($big->fresh()->unallocated())->toBe('50.00')
        ->and(app(Ledgers::class)->partyBalance($this->customer))->toBe('-50.00');
});
