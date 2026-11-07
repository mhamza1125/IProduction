<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Services\BomService;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Modules\Delivery\Services\Deliveries;
use App\Modules\Delivery\Services\Invoices;
use App\Modules\Delivery\Services\Packing;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Services\Ptcs;
use App\Modules\Production\Services\PtcTransactions;
use App\Modules\Production\Services\Worker;
use App\Modules\Reports\Livewire\OrderCost;
use App\Modules\Reports\Livewire\Show;
use App\Modules\Reports\Services\OrderCosting;
use App\Modules\Reports\Services\Reports;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Fixtures\TestDocument;

/**
 * One order made in a PTC from leather, delivered and invoiced in USD.
 */
beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->day = Carbon::parse('2026-11-05');
    $stage = Stage::query()->firstOrCreate(['name' => 'Cutting'], ['sort_order' => 1]);
    $glove = Item::factory()->finished()->create(['code' => 'GLV']);
    $this->glove = $glove->variants()->first();
    $route = ProductionRoute::create(['item_id' => $glove->id, 'name' => 'Std', 'is_default' => true]);
    $route->stages()->create(['stage_id' => $stage->id, 'sequence' => 1, 'wage_rate' => '10']);
    $leatherItem = Item::factory()->rawMaterial('kg')->create(['code' => 'LEA', 'default_purchase_cost' => '500']);
    $this->leather = $leatherItem->variants()->first();
    $bom = app(BomService::class)->newVersion($glove);
    $bom->lines()->create(['component_item_id' => $leatherItem->id, 'quantity' => '0.2', 'unit_id' => Unit::where('symbol', 'kg')->value('id'), 'wastage_percent' => 0]);
    app(BomService::class)->approve($bom);

    DB::transaction(function () {
        $doc = TestDocument::create(['number' => 'TD-1', 'document_date' => '2026-11-01']);
        $this->leatherLot = app(StockLedger::class)->createLot($this->leather->id, 'purchased', '550', Carbon::parse('2026-11-01'));
        app(StockLedger::class)->receive(new StockBucket($this->leather->id, null, StockStatus::AVAILABLE, Location::mainStore()->id, $this->leatherLot->id), '50', new StockSource($doc, Carbon::parse('2026-11-01')));
    });

    $this->customer = Customer::factory()->create(['name' => 'Hamburg Sports', 'currency_code' => 'USD']);
    $this->order = app(DocumentWorkflow::class)->approve(app(SalesOrders::class)->save(null, ['customer_id' => $this->customer->id, 'order_date' => '2026-11-01', 'due_date' => '2026-11-03'], [[
        'item_variant_id' => $this->glove->id, 'description' => '', 'quantity' => '100', 'unit_price' => '10', 'tax_rate' => '', 'due_date' => null]]));
    $line = $this->order->lines()->first();

    $ptcs = app(Ptcs::class);
    $tx = app(PtcTransactions::class);
    $ptc = $ptcs->approve($ptcs->save(null, ['item_variant_id' => $this->glove->id, 'planned_qty' => '100', 'route_id' => $route->id, 'sales_order_line_id' => $line->id, 'start_date' => '2026-11-02']));
    $cutter = Worker::from('employee', Employee::factory()->create(['employee_type' => Employee::TYPE_SALARY])->id, null);
    $tx->issueMaterial($ptc, null, $this->leather->id, '20', $cutter, $this->day);
    $tx->issueStage($ptc, $ptc->stages()->first(), '100', $cutter, $this->day);
    $tx->receiveStage($ptc, $ptc->stages()->first(), $cutter, '100', '0', $this->day);
    $tx->release($ptc, '100', $this->day);
    $this->ptc = $ptc->fresh();

    $d = app(Deliveries::class)->allocate(app(Deliveries::class)->save(null, ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-06'], [['sales_order_line_id' => $line->id, 'quantity' => '100']]));
    app(Packing::class)->addCarton($d, ['gross_weight' => '20'], [$d->lines()->value('id') => '100']);
    app(Packing::class)->complete($d);
    $this->delivery = app(Deliveries::class)->dispatch($d->fresh());
    app(Invoices::class)->post(app(DocumentWorkflow::class)->approve(app(Invoices::class)->save(null, ['customer_id' => $this->customer->id, 'invoice_date' => '2026-11-07'], [['delivery_line_id' => $this->delivery->lines()->value('id')]])));
});

it('shows the actual direct cost of an order against its planned cost, and estimates profit', function () {
    $c = app(OrderCosting::class)->forOrder($this->order);

    // 20 kg × 550 issued = 11,000 delivered at lot cost; planned 100 × 0.2 × 500 + 100 × 10 wage = 11,000.
    expect($c['actual'])->toBe('11000.00')
        ->and($c['planned'])->toBe('11000.00')
        ->and($c['revenue'])->toBe('1000.00')
        ->and($c['categories']['Materials issued to production'])->toBe('11000.00')
        ->and($c['lines'][0]['actual_unit'])->toBe('110.0000')
        ->and($c['sources']->pluck('label')->all())->toContain('PTC', 'Delivery', 'Invoice');

    Livewire::test(OrderCost::class, ['order' => $this->order])->set('reference_pkr', '280000')
        ->assertSee('269,000.00')->assertSee('Estimated profit');
    Livewire::test(OrderCost::class, ['order' => $this->order])->set('reference_rate', '280')->assertSee('280,000.00');
});

it('renders every report, prints and exports them', function () {
    foreach (app(Reports::class)->forUser(auth()->user())->flatten() as $report) {
        $this->get(route('reports.show', $report->key()))->assertOk();
        $this->get(route('reports.print', $report->key()))->assertOk();
    }
    $this->get(route('reports.index'))->assertOk()->assertSee('Trial balance');
    Livewire::test(Show::class, ['key' => 'customer-sales'])->call('export', 'csv')->assertFileDownloaded();
});

it('builds a trial balance that balances in each currency', function () {
    $report = app(Reports::class)->find('trial-balance');
    foreach (['PKR', 'USD'] as $currency) {
        $rows = $report->rows(['to' => '2026-12-31', 'currency' => $currency]);
        $t = $report->totals($rows);
        expect($t['debit'][''])->toBe($t['credit'][''])->and($rows)->not->toBeEmpty();
    }
});

it('traces a lot back to its source and forward to the customer', function () {
    $rows = app(Reports::class)->find('lot-trace')->rows(['lot' => $this->ptc->outputLot->lot_number]);

    expect($rows->pluck('direction')->all())->toContain('This lot', 'Source', 'Delivered')
        ->and($rows->firstWhere('direction', 'Source')['lot'])->toBe($this->leatherLot->lot_number)
        ->and($rows->firstWhere('direction', 'Delivered')['party'])->toBe('Hamburg Sports');
    $forward = app(Reports::class)->find('lot-trace')->rows(['lot' => $this->leatherLot->lot_number]);
    expect($forward->pluck('direction')->all())->toContain('Used in', 'Delivered');
});

it('reports receivables and order progress from posted data', function () {
    $rec = app(Reports::class)->find('receivables')->rows([]);
    expect($rec->sole())->toMatchArray(['customer' => 'Hamburg Sports', 'balance' => '1000.00', 'currency' => 'USD']);

    $stock = app(Reports::class)->find('stock-summary')->rows([]);
    expect($stock->firstWhere('sku', $this->leather->sku)['available'])->toBe('30.0000');
});

it('hides cost, profit and salary reports without permission and protects the screens', function () {
    $viewer = userWith(['reports.view', 'stock.view', 'invoices.view']);
    $keys = app(Reports::class)->forUser($viewer)->flatten()->map->key()->all();

    expect($keys)->toContain('stock-summary', 'customer-sales')->not->toContain('order-costs', 'profit-and-loss', 'loan-balances', 'money-balances');
    $this->actingAs($viewer)->get(route('reports.show', 'order-costs'))->assertForbidden();
    $this->actingAs($viewer)->get(route('reports.show', 'stock-summary'))->assertOk()->assertDontSee('Value (PKR)');
    $this->actingAs($viewer)->get(route('reports.order-cost', $this->order))->assertForbidden();
    $this->actingAs($viewer)->get(route('reports.print', 'stock-summary'))->assertForbidden();
    $this->actingAs(userWith([]))->get(route('reports.index'))->assertForbidden();
    Livewire::actingAs($viewer)->test(Show::class, ['key' => 'stock-summary'])->call('export', 'csv')->assertForbidden();
});

it('shows dashboard cards linked to their lists, by permission', function () {
    $this->get(route('dashboard'))->assertOk()->assertSee('Open orders')->assertSee('Late orders')->assertSee('Receivables')->assertSee('Top customers this year')->assertSee('Hamburg Sports');
    $this->actingAs(userWith(['sales_orders.view']))->get(route('dashboard'))->assertOk()->assertSee('Open orders')->assertDontSee('Cash and bank')->assertDontSee('Receivables');
});
