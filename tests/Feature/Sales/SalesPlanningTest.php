<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Services\BomService;
use App\Modules\Core\Models\Unit;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Actions\PostGoodsReceipt;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Sales\Livewire\Orders;
use App\Modules\Sales\Livewire\Planning as PlanningScreen;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\Planning;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Fixtures\TestDocument;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->customer = Customer::factory()->create(['name' => 'Hamburg Sports', 'currency_code' => 'PKR']);
    $this->glove = Item::factory()->finished()->create(['code' => 'GLV', 'can_buy' => true])->variants()->first();
    $this->planning = app(Planning::class);
    $this->stock = app(StockLedger::class);
});

function soStockIn(ItemVariant $variant, string $qty, string $status = StockStatus::AVAILABLE): void
{
    DB::transaction(function () use ($variant, $qty, $status) {
        $doc = TestDocument::create(['number' => 'TD-'.uniqid(), 'document_date' => '2026-11-01']);
        $lot = app(StockLedger::class)->createLot($variant->id, 'purchased', '100', Carbon::parse('2026-11-01'));
        app(StockLedger::class)->receive(new StockBucket($variant->id, null, $status, Location::mainStore()->id, $lot->id), $qty, new StockSource($doc, Carbon::parse('2026-11-01')));
    });
}

function salesOrder(ItemVariant $variant, string $qty = '100', bool $approve = true): SalesOrder
{
    $order = app(SalesOrders::class)->save(null, ['customer_id' => test()->customer->id, 'order_date' => '2026-11-01', 'due_date' => '2026-12-01'], [[
        'item_variant_id' => $variant->id, 'description' => '', 'quantity' => $qty, 'unit_price' => '12.5', 'tax_rate' => '', 'due_date' => null,
    ]]);

    return $approve ? app(DocumentWorkflow::class)->approve($order) : $order;
}

it('takes the currency from the customer and totals the lines', function () {
    $this->customer->update(['currency_code' => 'USD']);
    $order = salesOrder($this->glove, '100', false);

    expect($order->currency_code)->toBe('USD')
        ->and($order->total)->toBe('1250.00')
        ->and($order->number)->toStartWith('SO-');
});

it('reserves 20 free for an order of 100 and shows a shortfall of 80', function () {
    soStockIn($this->glove, '20');
    soStockIn($this->glove, '50', StockStatus::REJECTED);  // rejected stock never counts
    $line = salesOrder($this->glove)->lines()->first();

    expect($this->planning->picture($line)['next'])->toBe('Reserve free stock');
    expect($this->planning->reserveFree($line))->toBe('20.0000');

    $picture = $this->planning->picture($line->fresh());
    expect($picture['reserved'])->toBe('20.0000')
        ->and($picture['free'])->toBe('0.0000')
        ->and($picture['shortfall'])->toBe('80.0000')
        ->and($line->order->fresh()->status)->toBe('in_planning');
});

it('never reserves stock that another order holds', function () {
    soStockIn($this->glove, '30');
    $first = salesOrder($this->glove, '25')->lines()->first();
    $second = salesOrder($this->glove, '25')->lines()->first();

    $this->planning->reserveFree($first);
    expect($this->planning->reserveFree($second))->toBe('5.0000');
    expect(fn () => $this->planning->reserveFree($second->fresh()))->toThrow(DocumentException::class);
});

function soReceive(PurchaseOrder $po, string $qty): GoodsReceipt
{
    $line = $po->lines()->first();
    $grn = DB::transaction(function () use ($po, $line, $qty) {
        $grn = GoodsReceipt::create(['number' => app(DocumentNumber::class)->next('goods_receipt'), 'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id, 'receipt_date' => '2026-11-05']);
        $grn->lines()->create([
            'purchase_order_line_id' => $line->id, 'item_variant_id' => $line->item_variant_id, 'ordered_qty' => $line->quantity,
            'previously_accepted_qty' => $line->accepted_qty, 'received_qty' => $qty, 'accepted_qty' => $qty, 'rejected_qty' => '0',
            'rate' => $line->rate, 'location_id' => Location::mainStore()->id,
        ]);

        return $grn;
    });
    app(DocumentWorkflow::class)->approve($grn);

    return app(PostGoodsReceipt::class)($grn);
}

it('plans materials with wastage, existing stock and open purchase orders', function () {
    $kg = Unit::where('symbol', 'kg')->value('id');
    $leather = Item::factory()->rawMaterial('kg')->create(['code' => 'LEA']);
    $thread = Item::factory()->rawMaterial('pcs')->create(['code' => 'THR']);
    $bom = app(BomService::class)->newVersion($this->glove->item);
    $bom->lines()->create(['component_item_id' => $leather->id, 'quantity' => '0.2', 'unit_id' => $kg, 'wastage_percent' => 10]);
    $bom->lines()->create(['component_item_id' => $thread->id, 'quantity' => '2', 'unit_id' => Unit::where('symbol', 'pcs')->value('id'), 'wastage_percent' => 0]);
    app(BomService::class)->approve($bom);
    $route = ProductionRoute::create(['item_id' => $this->glove->item_id, 'name' => 'Standard', 'is_default' => true]);

    $leatherVariant = $leather->variants()->first();
    $threadVariant = $thread->variants()->first();
    soStockIn($leatherVariant, '5');

    $line = salesOrder($this->glove)->lines()->first();
    expect(fn () => $this->planning->savePlan($line, '0', '100', '0', null))->toThrow(DocumentException::class, 'route');
    expect(fn () => $this->planning->savePlan($line, '50', '60', '0', $route->id))->toThrow(DocumentException::class);
    $this->planning->savePlan($line, '0', '100', '0', $route->id);

    $vendor = Vendor::factory()->create();
    $this->planning->createPurchaseOrders($line->fresh(), [['item_variant_id' => $leatherVariant->id, 'quantity' => '10', 'vendor_id' => $vendor->id, 'rate' => '900']]);

    $rows = collect($this->planning->requirements($line->fresh()))->keyBy(fn ($r) => $r['variant']->id);
    // 100 × 0.2 kg + 10% wastage = 22 kg: 5 in stock, 10 on order, 7 short.
    expect($rows[$leatherVariant->id])->toMatchArray(['required' => '22.0000', 'free' => '5.0000', 'incoming' => '10.0000', 'shortage' => '7.0000'])
        ->and($rows[$threadVariant->id])->toMatchArray(['required' => '200.0000', 'free' => '0.0000', 'shortage' => '200.0000']);
});

it('creates one draft purchase order per vendor, linked to the order line', function () {
    $a = Item::factory()->rawMaterial()->create()->variants()->first();
    $b = Item::factory()->rawMaterial()->create()->variants()->first();
    [$v1, $v2] = Vendor::factory()->count(2)->create();
    $line = salesOrder($this->glove)->lines()->first();

    $pos = $this->planning->createPurchaseOrders($line, [
        ['item_variant_id' => $a->id, 'quantity' => '5', 'vendor_id' => $v1->id, 'rate' => '10'],
        ['item_variant_id' => $b->id, 'quantity' => '6', 'vendor_id' => $v1->id, 'rate' => '10'],
        ['item_variant_id' => $b->id, 'quantity' => '7', 'vendor_id' => $v2->id, 'rate' => '11'],
    ]);

    expect($pos)->toHaveCount(2)
        ->and($pos[0]->status)->toBe('draft')
        ->and($pos[0]->lines)->toHaveCount(2)
        ->and($line->allocations()->count())->toBe(3);
});

it('reserves bought finished goods to the order when they are received', function () {
    $this->glove->item->update(['can_buy' => true]);
    $line = salesOrder($this->glove)->lines()->first();
    $this->planning->savePlan($line, '100', '0', '0', null);
    $vendor = Vendor::factory()->create();
    [$po] = $this->planning->createPurchaseOrders($line->fresh(), [['item_variant_id' => $this->glove->id, 'quantity' => '100', 'vendor_id' => $vendor->id, 'rate' => '8']]);

    expect($this->planning->picture($line->fresh())['on_order'])->toBe('100.0000');

    soReceive(app(DocumentWorkflow::class)->approve($po), '60');

    $picture = $this->planning->picture($line->fresh());
    expect($picture['reserved'])->toBe('60.0000')
        ->and($picture['on_order'])->toBe('40.0000')
        ->and($picture['free'])->toBe('0.0000')
        ->and($picture['shortfall'])->toBe('0.0000');
});

it('releases reservations when an order is cancelled', function () {
    soStockIn($this->glove, '30');
    $order = salesOrder($this->glove, '20');
    $this->planning->reserveFree($order->lines()->first());

    expect(fn () => app(SalesOrders::class)->cancel($order->fresh(), ''))->toThrow(DocumentException::class);
    app(SalesOrders::class)->cancel($order->fresh(), 'Customer withdrew');

    expect($order->fresh()->status)->toBe('cancelled')
        ->and(Reservation::query()->where('status', Reservation::ACTIVE)->count())->toBe(0)
        ->and($this->stock->freeQuantity($this->glove->id))->toBe('30.0000');
});

it('needs override permission to edit an approved order, and never below what was delivered', function () {
    $order = salesOrder($this->glove, '100');
    $line = $order->lines()->first();
    $edit = fn (string $qty) => app(SalesOrders::class)->save($order->fresh(), ['customer_id' => $this->customer->id, 'order_date' => '2026-11-01'], [[
        'id' => $line->id, 'item_variant_id' => $this->glove->id, 'description' => '', 'quantity' => $qty, 'unit_price' => '12.5', 'tax_rate' => '', 'due_date' => null,
    ]]);

    $this->actingAs(userWith(['sales_orders.edit']));
    expect(fn () => $edit('120'))->toThrow(DocumentException::class, 'permission');

    $this->actingAs(userWith(['sales_orders.edit', 'sales_orders.override']));
    $line->forceFill(['delivered_qty' => '30'])->saveQuietly();
    expect(fn () => $edit('20'))->toThrow(DocumentException::class, 'delivered');

    $edit('120');
    expect($order->fresh()->total)->toBe('1500.00');
});

it('protects the screens with permissions', function () {
    $order = salesOrder($this->glove);

    $this->actingAs(userWith([]))->get(route('sales.orders.index'))->assertForbidden();
    $viewer = userWith(['sales_orders.view']);
    $this->actingAs($viewer)->get(route('sales.orders.index'))->assertOk()->assertSee($order->number);
    $this->actingAs($viewer)->get(route('sales.planning'))->assertForbidden();
    $this->actingAs($viewer)->get(route('sales.orders.create'))->assertForbidden();

    Livewire::actingAs($viewer)->test(Orders\Show::class, ['order' => $order])->call('cancel')->assertForbidden();
    Livewire::actingAs(userWith(['planning.view']))->test(PlanningScreen\Index::class)->call('select', $order->lines()->first()->id)->call('savePlan')->assertForbidden();
});

it('renders the sales screens', function () {
    soStockIn($this->glove, '10');
    $order = salesOrder($this->glove);

    $this->get(route('sales.orders.index'))->assertOk();
    $this->get(route('sales.orders.create'))->assertOk();
    $this->get(route('sales.orders.show', $order))->assertOk()->assertSee($order->number);
    $this->get(route('sales.orders.edit', $order))->assertOk();
    $this->get(route('sales.orders.print', $order))->assertOk();
    $this->get(route('sales.planning'))->assertOk()->assertSee($order->number);

    Livewire::test(PlanningScreen\Index::class)->call('select', $order->lines()->first()->id)
        ->call('reserve')->assertSee('Reserve free stock');
    expect($this->planning->picture($order->lines()->first())['reserved'])->toBe('10.0000');
});
