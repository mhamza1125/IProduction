<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Database\Seeders\CoreSeeder;
use App\Modules\Inventory\Database\Seeders\InventorySeeder;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TestDocument;

/*
| Acceptance test: "Two users cannot reserve or issue the same stock at the same time."
| User A holds the variant's stock rows locked inside a transaction; user B, on a
| second database connection, cannot lock them until A commits, and then sees A's result.
*/
it('serialises two users reserving the same stock', function () {
    $this->seed([CoreSeeder::class, InventorySeeder::class]);
    $this->actingAs(admin());

    $variant = Item::factory()->finished()->create()->variants()->first();
    $doc = TestDocument::create(['number' => 'TD-1', 'document_date' => '2026-11-01']);
    $orderA = TestDocument::create(['number' => 'SO-A', 'document_date' => '2026-11-01']);
    $ledger = app(StockLedger::class);

    DB::transaction(function () use ($ledger, $variant, $doc) {
        $lot = $ledger->createLot($variant->id, 'purchased', '100', Carbon::parse('2026-11-01'));
        $ledger->receive(new StockBucket($variant->id, null, StockStatus::AVAILABLE, Location::mainStore()->id, $lot->id), '10', new StockSource($doc, now()));
    });

    // A second, independent connection to the same database ("user B").
    config(['database.connections.mysql_b' => config('database.connections.mysql')]);
    $b = DB::connection('mysql_b');
    $b->statement('SET SESSION innodb_lock_wait_timeout = 1');

    // User A starts reserving all 10 and has not committed yet.
    DB::beginTransaction();
    $ledger->reserve($orderA, $variant->id, '10');

    // User B tries to lock the same stock rows to reserve or issue: it has to wait.
    $b->beginTransaction();
    $blocked = false;
    try {
        $b->select('SELECT id FROM stock_balances WHERE item_variant_id = ? FOR UPDATE', [$variant->id]);
    } catch (QueryException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout');
    }
    $b->rollBack();

    expect($blocked)->toBeTrue();

    // A commits; B now gets the lock and sees no free stock left.
    DB::commit();

    $b->beginTransaction();
    $b->select('SELECT id FROM stock_balances WHERE item_variant_id = ? FOR UPDATE', [$variant->id]);
    $reserved = $b->selectOne("SELECT COALESCE(SUM(quantity - consumed_quantity), 0) AS q FROM reservations WHERE item_variant_id = ? AND status = 'active'", [$variant->id])->q;
    $b->rollBack();

    expect((float) $reserved)->toBe(10.0)
        ->and($ledger->freeQuantity($variant->id))->toBe('0.0000');

    DB::disconnect('mysql_b');
});
