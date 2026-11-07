<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Services\BomService;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcTransaction;
use App\Modules\Production\Models\Rejection;
use App\Modules\Production\Models\WageWork;
use App\Modules\Production\Services\PtcCosting;
use App\Modules\Production\Services\Ptcs;
use App\Modules\Production\Services\PtcTransactions;
use App\Modules\Production\Services\Rejections;
use App\Modules\Production\Services\WageWorks;
use App\Modules\Production\Services\Worker;
use App\Modules\Sales\Services\Planning;
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
    $this->ptcs = app(Ptcs::class);
    $this->tx = app(PtcTransactions::class);
    $this->stock = app(StockLedger::class);
    $this->costing = app(PtcCosting::class);
    $this->day = Carbon::parse('2026-11-05');

    $this->stages = collect(['Cutting', 'Stitching', 'Embossing', 'Packing'])->map(fn ($n, $i) => Stage::query()->firstOrCreate(['name' => $n], ['sort_order' => $i + 1]));
    $this->glove = Item::factory()->finished()->create(['code' => 'GLV']);
    $this->variant = $this->glove->variants()->first();
    $this->route = ProductionRoute::create(['item_id' => $this->glove->id, 'name' => 'Standard', 'is_default' => true]);
    foreach ($this->stages as $i => $stage) {
        $this->route->stages()->create(['stage_id' => $stage->id, 'sequence' => $i + 1, 'is_optional' => $stage->name === 'Embossing', 'wage_rate' => $i === 1 ? '10' : null, 'contractor_rate' => $i === 1 ? '12' : null]);
    }
    $this->leather = Item::factory()->rawMaterial('kg')->create(['code' => 'LEA'])->variants()->first();
    $bom = app(BomService::class)->newVersion($this->glove);
    $bom->lines()->create(['component_item_id' => $this->leather->item_id, 'quantity' => '0.2', 'unit_id' => Unit::where('symbol', 'kg')->value('id'), 'wastage_percent' => 0]);
    app(BomService::class)->approve($bom);

    $this->cutter = Employee::factory()->create(['employee_type' => Employee::TYPE_SALARY]);
    $this->stitcher = Employee::factory()->create(['employee_type' => Employee::TYPE_WAGE]);
});

function prodStock(ItemVariant $variant, string $qty, string $cost, ?int $stageId = null): Lot
{
    return DB::transaction(function () use ($variant, $qty, $cost, $stageId) {
        $doc = TestDocument::create(['number' => 'TD-'.uniqid(), 'document_date' => '2026-11-01']);
        $lot = app(StockLedger::class)->createLot($variant->id, 'purchased', $cost, Carbon::parse('2026-11-01'));
        app(StockLedger::class)->receive(new StockBucket($variant->id, $stageId, StockStatus::AVAILABLE, Location::mainStore()->id, $lot->id), $qty, new StockSource($doc, Carbon::parse('2026-11-01')));

        return $lot;
    });
}

function newPtc(string $qty = '100', array $extra = []): Ptc
{
    $ptc = app(Ptcs::class)->save(null, $extra + ['item_variant_id' => test()->variant->id, 'planned_qty' => $qty, 'route_id' => test()->route->id, 'start_date' => '2026-11-05']);

    return app(Ptcs::class)->approve($ptc);
}

function emp(Employee $e): Worker
{
    return Worker::from('employee', $e->id, null);
}

/** Runs a PTC through every stage: cut, stitch, (skip embossing), pack. */
function runThrough(Ptc $ptc, string $qty, string $rejectAtStitch = '0'): Ptc
{
    $t = app(PtcTransactions::class);
    $s = $ptc->stages()->get()->keyBy(fn ($st) => $st->stage->name);
    $day = test()->day;
    $t->issueStage($ptc, $s['Cutting'], $qty, emp(test()->cutter), $day);
    $t->receiveStage($ptc, $s['Cutting'], emp(test()->cutter), $qty, '0', $day);
    $t->issueStage($ptc, $s['Stitching'], $qty, emp(test()->stitcher), $day);
    $good = Decimal::sub($qty, $rejectAtStitch, 4);
    $t->receiveStage($ptc, $s['Stitching'], emp(test()->stitcher), $good, $rejectAtStitch, $day, $rejectAtStitch !== '0' ? 'Bad stitching' : null);
    $t->skipStage($ptc, $s['Embossing'], 'Plain glove', $day);
    $t->issueStage($ptc, $s['Packing'], $good, emp(test()->cutter), $day);
    $t->receiveStage($ptc, $s['Packing'], emp(test()->cutter), $good, '0', $day);

    return $ptc->fresh();
}

it('copies the route and BOM and creates the output lot on approval', function () {
    $ptc = newPtc();

    expect($ptc->status)->toBe('approved')
        ->and($ptc->number)->toStartWith('PTC-')
        ->and($ptc->stages()->count())->toBe(4)
        ->and($ptc->materials()->first()->per_unit)->toBe('0.200000')
        ->and($ptc->outputLot->origin)->toBe('made');
});

it('runs the normal flow: issue material, stages, wage work, release at cost', function () {
    prodStock($this->leather, '30', '500');
    $ptc = newPtc('100');
    $this->tx->issueMaterial($ptc, $ptc->stages()->first(), $this->leather->id, '20', emp($this->cutter), $this->day);

    expect($this->stock->onHand($this->leather->id))->toBe('10.0000')
        ->and($this->stock->onHand($this->leather->id, null, StockStatus::IN_PRODUCTION))->toBe('20.0000');

    $ptc = runThrough($ptc, '100', '4');

    // The wage employee's 96 accepted pieces at Rs 10 wait for review; rejected pieces are unpaid.
    $work = WageWork::query()->sole();
    expect($work->quantity)->toBe('96.0000')->and($work->amount)->toBe('960.00')->and($work->status)->toBe('pending');
    app(WageWorks::class)->approve($work);
    expect(app(Ledgers::class)->partyBalance($this->stitcher))->toBe('-960.00');

    expect(Rejection::query()->sole()->quantity)->toBe('4.0000')
        ->and($this->stock->onHand($this->variant->id, $this->stages[1]->id, StockStatus::REJECTED))->toBe('4.0000');

    $this->tx->release($ptc, '96', $this->day);
    $ptc->refresh();

    // 20 kg × 500 + 960 wages = 10,960 over 96 good pieces.
    expect($this->stock->onHand($this->variant->id))->toBe('96.0000')
        ->and($ptc->outputLot->fresh()->unit_cost)->toBe('114.1667')
        ->and($this->stock->onHand($this->leather->id, null, StockStatus::IN_PRODUCTION))->toBe('0.8000');

    $this->ptcs->complete($ptc);
    expect($ptc->fresh()->status)->toBe('completed')
        ->and($this->stock->onHand($this->leather->id, null, StockStatus::IN_PRODUCTION))->toBe('0.0000')
        ->and($this->costing->pool($ptc))->toBe('0.00');
});

it('blocks receiving more than was issued, skipping a mandatory stage, and issuing beyond what is available', function () {
    $ptc = newPtc('50');
    $s = $ptc->stages()->get()->keyBy(fn ($st) => $st->stage->name);
    $this->tx->issueStage($ptc, $s['Cutting'], '50', emp($this->cutter), $this->day);
    $this->tx->receiveStage($ptc, $s['Cutting'], emp($this->cutter), '30', '0', $this->day);

    $this->actingAs(userWith(['ptcs.view']));
    expect(fn () => $this->tx->receiveStage($ptc, $s['Cutting'], emp($this->cutter), '25', '0', $this->day))->toThrow(DocumentException::class, 'Over-output');
    expect(fn () => $this->tx->skipStage($ptc, $s['Stitching'], 'no', $this->day))->toThrow(DocumentException::class, 'mandatory');
    expect(fn () => $this->tx->issueStage($ptc, $s['Stitching'], '31', emp($this->stitcher), $this->day))->toThrow(DocumentException::class, 'Only 30');
    // Packing cannot take pieces that skipped stitching.
    expect(fn () => $this->tx->issueStage($ptc, $s['Packing'], '1', emp($this->cutter), $this->day))->toThrow(DocumentException::class);
});

it('can start at a middle stage from part-made stock', function () {
    // Bought part-made gloves, already stitched (stage 2).
    $bought = prodStock($this->variant, '40', '60', $this->stages[1]->id);
    $ptc = newPtc('40', ['start_stage_id' => $this->stages[2]->id]);
    $s = $ptc->stages()->get()->keyBy(fn ($st) => $st->stage->name);

    expect($s->keys()->all())->toBe(['Embossing', 'Packing']);

    $this->tx->issueStage($ptc, $s['Embossing'], '40', emp($this->cutter), $this->day);
    $this->tx->receiveStage($ptc, $s['Embossing'], emp($this->cutter), '40', '0', $this->day);
    $this->tx->issueStage($ptc, $s['Packing'], '40', emp($this->cutter), $this->day);
    $this->tx->receiveStage($ptc, $s['Packing'], emp($this->cutter), '40', '0', $this->day);
    $this->tx->release($ptc, '40', $this->day);

    expect($this->stock->onHand($this->variant->id))->toBe('40.0000')
        ->and($this->stock->onHand($this->variant->id, $this->stages[1]->id))->toBe('0.0000')
        ->and($ptc->fresh()->outputLot->unit_cost)->toBe('60.0000')
        ->and($ptc->fresh()->outputLot->inputs()->pluck('source_lot_id')->all())->toContain($bought->id);
});

it('issues extra material during an active stage with a reason and keeps the full history', function () {
    prodStock($this->leather, '30', '500');
    $ptc = newPtc('100');
    $cut = $ptc->stages()->first();
    $this->tx->issueMaterial($ptc, $cut, $this->leather->id, '20', emp($this->cutter), $this->day);
    $this->tx->issueStage($ptc, $cut, '100', emp($this->cutter), $this->day);

    expect(fn () => $this->tx->issueMaterial($ptc, $cut, $this->leather->id, '2', emp($this->cutter), $this->day, true))->toThrow(DocumentException::class, 'reason');
    $this->tx->issueMaterial($ptc, $cut, $this->leather->id, '2', emp($this->cutter), $this->day, true, 'Leather torn');
    $this->tx->returnToStore($ptc, $this->leather->id, $ptc->transactions()->where('type', 'material_issue')->value('lot_id'), Location::productionFloor()->id, '1', $this->day);

    expect($ptc->transactions()->pluck('type')->all())->toBe(['material_issue', 'stage_issue', 'extra_issue', 'return_to_store'])
        ->and(PtcTransaction::query()->where('type', 'extra_issue')->value('reason'))->toBe('Leather torn')
        ->and($this->costing->byCategory($ptc)->all())->toMatchArray(['material' => '9500.00', 'extra_material' => '1000.00']);

    $row = $this->costing->reconciliation($ptc)->first();
    expect($row)->toMatchArray(['issued' => '20.0000', 'extra' => '2.0000', 'returned' => '1.0000', 'held' => '21.0000']);
});

it('rolls child PTC cost up to the order and reserves released goods to it', function () {
    $customer = Customer::factory()->create(['currency_code' => 'PKR']);
    $order = app(SalesOrders::class)->save(null, ['customer_id' => $customer->id, 'order_date' => '2026-11-01'], [[
        'item_variant_id' => $this->variant->id, 'description' => '', 'quantity' => '100', 'unit_price' => '300', 'tax_rate' => '', 'due_date' => null,
    ]]);
    $order = app(DocumentWorkflow::class)->approve($order);
    $line = $order->lines()->first();
    prodStock($this->leather, '30', '500');

    $parent = newPtc('50', ['sales_order_line_id' => $line->id]);
    $child = app(Ptcs::class)->approve($this->ptcs->createChild($parent, '50', null));
    expect($child->sales_order_line_id)->toBe($line->id)
        ->and(app(Planning::class)->picture($line->fresh())['in_production'])->toBe('100.0000');

    $this->tx->issueMaterial($parent, null, $this->leather->id, '10', emp($this->cutter), $this->day);
    $this->tx->issueMaterial($child, null, $this->leather->id, '10', emp($this->cutter), $this->day);

    expect($this->costing->orderLineCost($line))->toBe('10000.00');

    expect(fn () => $this->ptcs->complete($parent))->toThrow(DocumentException::class, 'child');

    runThrough($child, '50');
    $this->tx->release($child->fresh(), '50', $this->day);
    expect(Reservation::query()->where('demand_id', $line->id)->where('status', 'active')->sum('quantity'))->toEqual(50);
    expect($order->fresh()->status)->toBe('in_progress');
});

it('merges only PTCs of the same order, variant, route and stage', function () {
    $customer = Customer::factory()->create(['currency_code' => 'PKR']);
    $lines = collect([1, 2])->map(function () use ($customer) {
        $o = app(SalesOrders::class)->save(null, ['customer_id' => $customer->id, 'order_date' => '2026-11-01'], [[
            'item_variant_id' => $this->variant->id, 'description' => '', 'quantity' => '10', 'unit_price' => '1', 'tax_rate' => '', 'due_date' => null]]);

        return app(DocumentWorkflow::class)->approve($o)->lines()->first();
    });
    $a = newPtc('10', ['sales_order_line_id' => $lines[0]->id]);
    $b = newPtc('10', ['sales_order_line_id' => $lines[1]->id]);
    $c = newPtc('10');
    $d = newPtc('10');

    expect(fn () => $this->ptcs->merge($a, $b, $this->stages[0]->id, '5'))->toThrow(DocumentException::class, 'different orders')
        ->and(fn () => $this->ptcs->merge($a, $c, $this->stages[0]->id, '5'))->toThrow(DocumentException::class, 'different orders');

    $cut = fn (Ptc $p) => $p->stages()->first();
    $this->tx->issueStage($c, $cut($c), '10', emp($this->cutter), $this->day);
    $this->tx->receiveStage($c, $cut($c), emp($this->cutter), '10', '0', $this->day);
    $this->ptcs->merge($c, $d, $this->stages[0]->id, '10');

    expect($this->stock->onHand($this->variant->id, $this->stages[0]->id, StockStatus::AVAILABLE, null, $d->output_lot_id))->toBe('10.0000')
        ->and($c->fresh()->merged_out_qty)->toBe('10.0000')
        ->and($c->transactions()->pluck('type')->all())->toContain('merge_out')
        ->and($d->links()->where('type', 'merge')->value('quantity'))->toBe('10.0000');
});

it('pays a contractor at the stage rate for accepted pieces', function () {
    $contractor = Contractor::factory()->create();
    $ptc = newPtc('20');
    $s = $ptc->stages()->get()->keyBy(fn ($st) => $st->stage->name);
    $this->tx->issueStage($ptc, $s['Cutting'], '20', emp($this->cutter), $this->day);
    $this->tx->receiveStage($ptc, $s['Cutting'], emp($this->cutter), '20', '0', $this->day);
    $c = Worker::from('contractor', null, $contractor->id);
    $this->tx->issueStage($ptc, $s['Stitching'], '20', $c, $this->day);

    expect($this->stock->onHand($this->variant->id, $this->stages[1]->id, StockStatus::IN_PRODUCTION, Location::forContractor($contractor)->id))->toBe('20.0000')
        ->and($this->tx->holders($ptc)[0])->toMatchArray(['worker' => $contractor->name, 'pending' => '20.0000']);

    $this->tx->receiveStage($ptc, $s['Stitching'], $c, '18', '2', $this->day, 'Torn');

    expect(app(Ledgers::class)->partyBalance($contractor))->toBe('-216.00')
        ->and($this->costing->byCategory($ptc)['contractor'])->toBe('216.00')
        ->and(Rejection::query()->sole()->responsible_id)->toBe($contractor->id);
});

it('decides rejections: rework, scrap, sell and recover, keeping history', function () {
    $ptc = newPtc('20');
    runThrough($ptc, '20', '8');
    $rejection = Rejection::query()->sole();
    $rej = app(Rejections::class);
    $wf = app(DocumentWorkflow::class);

    $other = newPtc('5');
    $rework = $rej->decide($rejection, ['decision' => 'rework', 'quantity' => '3', 'target_ptc_id' => $ptc->id, 'target_stage_id' => $this->stages[1]->id, 'reason' => 'Restitch']);
    $scrap = $rej->decide($rejection, ['decision' => 'scrap', 'quantity' => '2', 'reason' => 'Beyond repair']);
    $sell = $rej->decide($rejection, ['decision' => 'sell', 'quantity' => '3', 'reason' => 'Seconds']);
    $charge = $rej->decide($rejection, ['decision' => 'recover', 'amount' => '150', 'reason' => 'Careless work']);

    expect(fn () => $rej->decide($rejection, ['decision' => 'scrap', 'quantity' => '1', 'reason' => 'x']))->toThrow(DocumentException::class, 'undecided');
    expect(fn () => $rej->decide($rejection, ['decision' => 'rework', 'quantity' => '1', 'target_ptc_id' => $other->id, 'target_stage_id' => 999, 'reason' => 'x']))->toThrow(DocumentException::class);

    // Decisions need approval before posting.
    expect(fn () => $rej->post($scrap))->toThrow(DocumentException::class);
    foreach ([$rework, $scrap, $sell, $charge] as $d) {
        $rej->post($wf->approve($d));
    }

    expect($this->stock->onHand($this->variant->id, $this->stages[1]->id, StockStatus::REJECTED))->toBe('3.0000')
        ->and($this->stock->onHand($this->variant->id, $this->stages[1]->id, StockStatus::AVAILABLE, null, $ptc->output_lot_id))->toBe('3.0000')
        ->and($rejection->fresh()->status)->toBe('decided')
        ->and($rejection->decisions()->where('status', 'posted')->count())->toBe(4)
        ->and(app(Ledgers::class)->partyBalance($this->stitcher))->toBe('150.00');
});

it('never uses rejected stock in normal issues', function () {
    prodStock($this->leather, '10', '500');
    $lot = Lot::query()->sole();
    app(Rejections::class)->rejectStock($this->leather->id, null, $lot->id, Location::mainStore()->id, '10', 'Water damage', $this->day);
    $ptc = newPtc('10');

    expect(fn () => $this->tx->issueMaterial($ptc, null, $this->leather->id, '1', emp($this->cutter), $this->day))->toThrow(DocumentException::class, 'Not enough');
});

it('closes a PTC early, moving finished pieces at a stage to store', function () {
    $ptc = newPtc('30');
    $s = $ptc->stages()->get()->keyBy(fn ($st) => $st->stage->name);
    $this->tx->issueStage($ptc, $s['Cutting'], '30', emp($this->cutter), $this->day);
    $this->tx->receiveStage($ptc, $s['Cutting'], emp($this->cutter), '30', '0', $this->day);

    expect(fn () => $this->ptcs->complete($ptc))->toThrow(DocumentException::class, 'still at a stage');
    expect(fn () => $this->ptcs->closeEarly($ptc, ''))->toThrow(DocumentException::class);
    $this->ptcs->closeEarly($ptc, 'Order reduced; cut pieces to store');

    expect($ptc->fresh()->status)->toBe('closed_early')
        ->and($this->stock->onHand($this->variant->id, $this->stages[0]->id, StockStatus::AVAILABLE, Location::mainStore()->id))->toBe('30.0000');
    expect(fn () => $this->tx->issueStage($ptc->fresh(), $s['Stitching'], '1', emp($this->stitcher), $this->day))->toThrow(DocumentException::class, 'closed early');
});
