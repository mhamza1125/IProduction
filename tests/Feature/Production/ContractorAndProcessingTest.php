<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Actions\PostMaterialProcess;
use App\Modules\Production\Models\Rejection;
use App\Modules\Production\Services\ContractorJobs;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\TestDocument;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->stock = app(StockLedger::class);
    $this->jobs = app(ContractorJobs::class);
    $this->day = Carbon::parse('2026-11-05');
    $this->contractor = Contractor::factory()->create(['name' => 'Ali Stitching']);
    $this->ball = Item::factory()->finished()->create(['code' => 'BALL'])->variants()->first();
    $this->panel = Item::factory()->rawMaterial()->create(['code' => 'PANEL'])->variants()->first();
});

function cpStock(ItemVariant $variant, string $qty, string $cost): Lot
{
    return DB::transaction(function () use ($variant, $qty, $cost) {
        $doc = TestDocument::create(['number' => 'TD-'.uniqid(), 'document_date' => '2026-11-01']);
        $lot = app(StockLedger::class)->createLot($variant->id, 'purchased', $cost, Carbon::parse('2026-11-01'));
        app(StockLedger::class)->receive(new StockBucket($variant->id, null, StockStatus::AVAILABLE, Location::mainStore()->id, $lot->id), $qty, new StockSource($doc, Carbon::parse('2026-11-01')));

        return $lot;
    });
}

function approvedJob(string $qty = '100', string $rate = '50', string $expected = '3200')
{
    $job = app(ContractorJobs::class)->save(null, ['contractor_id' => test()->contractor->id, 'job_date' => '2026-11-05'],
        [['item_variant_id' => test()->ball->id, 'stage_id' => null, 'quantity' => $qty, 'rate' => $rate]],
        [['item_variant_id' => test()->panel->id, 'expected_qty' => $expected]]);

    return app(ContractorJobs::class)->approve($job);
}

it('reconciles a contractor job: material issued, used, returned, wasted and still held', function () {
    $panels = cpStock($this->panel, '4000', '2');
    $job = approvedJob();

    $this->jobs->issueMaterial($job, $this->panel->id, '3400', $this->day);
    expect($job->fresh()->status)->toBe('material_issued')
        ->and($this->stock->onHand($this->panel->id, null, StockStatus::IN_PRODUCTION, Location::forContractor($this->contractor)->id))->toBe('3400.0000');

    // 60 balls back: 58 good, 2 rejected; the expected 32 panels each are used.
    $this->jobs->receive($job, $job->lines->first(), '60', '58', '2', $this->day, rejectReason: 'Seams open');
    $this->jobs->returnMaterial($job, $this->panel->id, $panels->id, '100', $this->day);
    $this->jobs->wastage($job, $this->panel->id, $panels->id, '20', 'Cut wrong', $this->day);

    $row = $this->jobs->reconciliation($job->fresh())->first();
    expect($row)->toMatchArray(['issued' => '3400.0000', 'expected' => '1920.0000', 'consumed' => '1920.0000', 'returned' => '100.0000', 'wastage' => '20.0000', 'held' => '1360.0000']);

    expect($job->fresh()->status)->toBe('partly_received')
        ->and(app(Ledgers::class)->partyBalance($this->contractor))->toBe('-2900.00')
        ->and($this->stock->onHand($this->ball->id))->toBe('58.0000')
        ->and(Rejection::query()->sole()->responsible_id)->toBe($this->contractor->id);

    // Lot cost = (1920 panels × 2 + 58 × 50) ÷ 60 pieces stocked.
    expect(Lot::query()->where('origin', 'contractor_made')->sole()->unit_cost)->toBe('112.3333');

    expect(fn () => $this->jobs->close($job->fresh(), 'Done'))->toThrow(DocumentException::class, 'still holds');
});

it('follows each over-receipt option for 105 received against a job of 100', function (string $action, string $stocked, string $paid) {
    cpStock($this->panel, '4000', '2');
    $job = approvedJob('100', '50', '0');

    expect(fn () => $this->jobs->receive($job, $job->lines->first(), '105', '105', '0', $this->day))->toThrow(DocumentException::class, 'Choose');

    $tx = $this->jobs->receive($job, $job->lines->first(), '105', '105', '0', $this->day, $action);
    expect($tx->accepted_qty)->toBe($stocked)->and($tx->paid_qty)->toBe($paid)
        ->and($this->stock->onHand($this->ball->id))->toBe($stocked)
        ->and($job->fresh()->status)->toBe('received');
})->with([
    'accept and pay' => ['accept_pay', '105.0000', '105.0000'],
    'accept as free' => ['accept_free', '105.0000', '100.0000'],
    'return the extra' => ['return_extra', '100.0000', '100.0000'],
]);

it('processes several inputs into variable output with yield and cost per unit', function () {
    $thaan = Item::factory()->rawMaterial('m')->create(['code' => 'THAAN'])->variants()->first();
    $lining = Item::factory()->rawMaterial('m')->create(['code' => 'LINING'])->variants()->first();
    $cut = Item::factory()->rawMaterial()->create(['code' => 'CUT-PIECE'])->variants()->first();
    cpStock($thaan, '100', '300');
    cpStock($lining, '50', '100');
    $worker = Employee::factory()->create(['employee_type' => Employee::TYPE_WAGE]);

    $post = app(PostMaterialProcess::class);
    $process = $post->save(null, ['process_date' => '2026-11-05', 'process_name' => 'Cut thaan with lining', 'worker_type' => 'employee', 'employee_id' => $worker->id, 'labor_cost' => '2000', 'recoverable_value' => '500'],
        [['item_variant_id' => $thaan->id, 'quantity' => '40'], ['item_variant_id' => $lining->id, 'quantity' => '20']],
        [['item_variant_id' => $cut->id, 'expected_qty' => '400', 'quantity' => '385']],
        [['quantity' => '3', 'loss_type' => 'wastage', 'reason' => 'Edges']]);

    expect(fn () => $post($process))->toThrow(DocumentException::class);
    $post(app(DocumentWorkflow::class)->approve($process));
    $process->refresh();

    // (40 × 300 + 20 × 100) + 2000 − 500 = 15,500 over 385 pieces.
    expect($process->status)->toBe('posted')
        ->and($process->input_cost)->toBe('14000.00')
        ->and($process->output_unit_cost)->toBe('40.2597')
        ->and($this->stock->onHand($thaan->id))->toBe('60.0000')
        ->and($this->stock->onHand($cut->id))->toBe('385.0000')
        ->and(Lot::query()->where('item_variant_id', $cut->id)->sole()->inputs()->count())->toBe(2)
        ->and($post->yield($process->load('lines')))->toMatchArray(['expected' => '400.0000', 'actual' => '385.0000', 'wastage_percent' => '5.00'])
        ->and(app(Ledgers::class)->partyBalance($worker))->toBe('-2000.00');
});

it('rolls back the whole process when one input is short', function () {
    $a = Item::factory()->rawMaterial()->create()->variants()->first();
    $b = Item::factory()->rawMaterial()->create()->variants()->first();
    $out = Item::factory()->rawMaterial()->create()->variants()->first();
    cpStock($a, '10', '1');
    cpStock($b, '1', '1');
    $post = app(PostMaterialProcess::class);
    $process = app(DocumentWorkflow::class)->approve($post->save(null, ['process_date' => '2026-11-05', 'process_name' => 'Mix'],
        [['item_variant_id' => $a->id, 'quantity' => '5'], ['item_variant_id' => $b->id, 'quantity' => '2']], [['item_variant_id' => $out->id, 'quantity' => '6']], []));

    expect(fn () => $post($process))->toThrow(DocumentException::class, 'short by 1');
    expect($this->stock->onHand($a->id))->toBe('10.0000')->and($process->fresh()->status)->toBe('approved');
});
