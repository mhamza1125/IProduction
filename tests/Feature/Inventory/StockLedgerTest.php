<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Database\Seeders\CoreSeeder;
use App\Modules\Core\Models\Stage;
use App\Modules\Inventory\Database\Seeders\InventorySeeder;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Contractor;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TestDocument;

beforeEach(function () {
    $this->seed([CoreSeeder::class, InventorySeeder::class]);
    $this->actingAs(admin());
    $this->stock = app(StockLedger::class);
    $this->main = Location::mainStore();
    $this->doc = DB::transaction(fn () => TestDocument::create(['number' => 'TD-1', 'document_date' => '2026-11-01']));
    $this->glove = Item::factory()->finished()->create(['code' => 'GLV'])->variants()->first();
});

/** Receives stock into a new lot in the main store. */
function stockIn(ItemVariant $variant, string $qty, string $cost = '100', string $date = '2026-11-01', ?int $stageId = null, string $status = StockStatus::AVAILABLE): Lot
{
    return DB::transaction(function () use ($variant, $qty, $cost, $date, $stageId, $status) {
        $ledger = app(StockLedger::class);
        $lot = $ledger->createLot($variant->id, 'purchased', $cost, Carbon::parse($date));
        $ledger->receive(new StockBucket($variant->id, $stageId, $status, Location::mainStore()->id, $lot->id), $qty, new StockSource(test()->doc, Carbon::parse($date)));

        return $lot;
    });
}

function demand(string $number): TestDocument
{
    return DB::transaction(fn () => TestDocument::create(['number' => $number, 'document_date' => '2026-11-01']));
}

it('records receipts as lots and movements, and keeps balances in step', function () {
    $lot = stockIn($this->glove, '40', '250');

    expect($lot->lot_number)->toBe('LOT-26-0001')
        ->and($this->stock->onHand($this->glove->id))->toBe('40.0000')
        ->and(StockMovement::first())->qty_in->toBe('40.0000')->total_cost->toBe('10000.00');
});

it('shows free stock as available minus reservations: 100 ordered, 20 free, shortfall 80', function () {
    stockIn($this->glove, '20');
    $order = demand('SO-26-0001');

    $reservation = DB::transaction(fn () => $this->stock->reserve($order, $this->glove->id, '20'));

    expect($reservation->quantity)->toBe('20.0000')
        ->and($this->stock->freeQuantity($this->glove->id))->toBe('0.0000')
        ->and(Decimal::sub('100', $reservation->quantity, 4))->toBe('80.0000');

    expect(fn () => DB::transaction(fn () => $this->stock->reserve(demand('SO-26-0002'), $this->glove->id, '1')))
        ->toThrow(DocumentException::class, 'Only 0.0000 free');
});

it('blocks negative stock and rolls back the whole document on a shortage', function () {
    stockIn($this->glove, '10');
    $leather = Item::factory()->rawMaterial('kg')->create()->variants()->first();
    stockIn($leather, '5');

    expect(fn () => DB::transaction(function () {
        $source = new StockSource($this->doc, Carbon::parse('2026-11-02'));
        $this->stock->issue($this->glove->id, '4', $source);          // line 1: fine
        $this->stock->issue(ItemVariant::where('id', '!=', $this->glove->id)->first()->id, '6', $source); // line 2: short
    }))->toThrow(DocumentException::class, 'short by 1');

    // Nothing from line 1 was saved.
    expect($this->stock->onHand($this->glove->id))->toBe('10.0000')
        ->and(StockMovement::where('qty_out', '>', 0)->count())->toBe(0);
});

it('issues FIFO and never uses another order\'s reservation', function () {
    $old = stockIn($this->glove, '10', '90', '2026-10-01');
    $new = stockIn($this->glove, '10', '110', '2026-11-01');
    $orderA = demand('SO-A');
    $orderB = demand('SO-B');

    DB::transaction(fn () => $this->stock->reserve($orderA, $this->glove->id, '15'));

    // B may only use the 5 that are free.
    expect(fn () => DB::transaction(fn () => $this->stock->issue($this->glove->id, '6', new StockSource($this->doc, now()), demand: $orderB)))
        ->toThrow(DocumentException::class);

    $issued = DB::transaction(fn () => $this->stock->issue($this->glove->id, '5', new StockSource($this->doc, now()), demand: $orderB));
    expect($issued[0]['bucket']->lotId)->toBe($old->id); // oldest lot first

    // A takes its 15 and its reservation is consumed.
    DB::transaction(fn () => $this->stock->issue($this->glove->id, '15', new StockSource($this->doc, now()), demand: $orderA));

    expect(Reservation::first())->status->toBe(Reservation::CONSUMED)->consumed_quantity->toBe('15.0000')
        ->and($this->stock->onHand($this->glove->id))->toBe('0.0000');
});

it('uses an order\'s reserved lot first and protects lot-level reservations', function () {
    $old = stockIn($this->glove, '10', '90', '2026-10-01');
    $new = stockIn($this->glove, '10', '110', '2026-11-01');
    $orderA = demand('SO-A');

    DB::transaction(fn () => $this->stock->reserve($orderA, $this->glove->id, '10', lotId: $new->id));

    // Another issue can't touch the reserved newer lot.
    expect(fn () => DB::transaction(fn () => $this->stock->issue($this->glove->id, '1', new StockSource($this->doc, now()), lotId: $new->id)))
        ->toThrow(DocumentException::class);

    // The order's own issue takes its reserved lot before the older free one.
    $issued = DB::transaction(fn () => $this->stock->issue($this->glove->id, '10', new StockSource($this->doc, now()), demand: $orderA));
    expect(collect($issued)->pluck('bucket.lotId')->unique()->all())->toBe([$new->id]);
});

it('keeps statuses and stages separate: rejected stock is never issued', function () {
    $stitching = Stage::where('name', 'Stitching')->first();
    $lot = stockIn($this->glove, '40', stageId: $stitching->id);
    stockIn($this->glove, '7', stageId: $stitching->id, status: StockStatus::REJECTED);

    $bucket = new StockBucket($this->glove->id, $stitching->id, StockStatus::AVAILABLE, $this->main->id, $lot->id);
    DB::transaction(fn () => $this->stock->transfer($bucket, $bucket->with(status: StockStatus::IN_PRODUCTION, locationId: Location::productionFloor()->id), '25', new StockSource($this->doc, now())));

    expect($this->stock->onHand($this->glove->id, $stitching->id))->toBe('15.0000')
        ->and($this->stock->onHand($this->glove->id, $stitching->id, StockStatus::IN_PRODUCTION))->toBe('25.0000')
        ->and($this->stock->onHand($this->glove->id, $stitching->id, StockStatus::REJECTED))->toBe('7.0000')
        ->and($this->stock->onHand($this->glove->id))->toBe('0.0000'); // nothing at "no stage"

    expect(fn () => DB::transaction(fn () => $this->stock->issue($this->glove->id, '16', new StockSource($this->doc, now()), stageId: $stitching->id)))
        ->toThrow(DocumentException::class, 'short by 1');
});

it('keeps the lot cost when stock moves between statuses and locations', function () {
    $lot = stockIn($this->glove, '10', '123.4567');
    $bucket = new StockBucket($this->glove->id, null, StockStatus::AVAILABLE, $this->main->id, $lot->id);

    [$out, $in] = DB::transaction(fn () => $this->stock->transfer($bucket, $bucket->with(locationId: Location::productionFloor()->id), '4', new StockSource($this->doc, now())));

    expect($in->unit_cost)->toBe('123.4567')->and($in->total_cost)->toBe('493.83')->and($out->lot_id)->toBe($in->lot_id);
});

it('releases and reallocates reservations only with a reason', function () {
    stockIn($this->glove, '10');
    $a = demand('SO-A');
    $b = demand('SO-B');
    $reservation = DB::transaction(fn () => $this->stock->reserve($a, $this->glove->id, '6'));

    expect(fn () => $this->stock->release($reservation, ''))->toThrow(DocumentException::class, 'reason');

    $moved = $this->stock->reallocate($reservation, $b, 'Customer B is urgent');
    expect($reservation->fresh()->status)->toBe(Reservation::RELEASED)
        ->and($moved->demand_id)->toBe($b->id)->and($moved->quantity)->toBe('6.0000');

    $this->stock->release($moved, 'Order cancelled');
    expect($this->stock->freeQuantity($this->glove->id))->toBe('10.0000');
});

it('never edits or deletes stock movements, and rebuilds balances from them', function () {
    stockIn($this->glove, '10');
    DB::transaction(fn () => $this->stock->issue($this->glove->id, '3', new StockSource($this->doc, now())));

    $movement = StockMovement::first();
    expect(fn () => $movement->update(['qty_in' => '99']))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class);

    StockBalance::query()->update(['quantity' => '999']); // corrupt the cache
    app(StockLedger::class)->rebuildBalances();

    expect($this->stock->onHand($this->glove->id))->toBe('7.0000');
});

it('creates a location for every contractor automatically', function () {
    $contractor = Contractor::factory()->create(['name' => 'Al-Noor Stitching']);

    expect(Location::where('contractor_id', $contractor->id)->first())
        ->type->toBe(Location::CONTRACTOR)->name->toBe('Al-Noor Stitching');
});

it('refuses stock changes outside a transaction', function () {
    DB::rollBack();

    try {
        app(StockLedger::class)->reserve(demand('X'), $this->glove->id, '1');
    } finally {
        DB::beginTransaction();
    }
})->throws(LogicException::class);
