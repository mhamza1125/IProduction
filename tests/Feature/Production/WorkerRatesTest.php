<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Services\BomService;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Livewire\WorkerRates\Index as RatesScreen;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcCost;
use App\Modules\Production\Models\WageWork;
use App\Modules\Production\Models\WorkerRate;
use App\Modules\Production\Services\Ptcs;
use App\Modules\Production\Services\PtcTransactions;
use App\Modules\Production\Services\WageWorks;
use App\Modules\Production\Services\Worker;
use Carbon\Carbon;
use Livewire\Livewire;

/**
 * Individual piece rates: Stage "Stitching" pays Rs. 10 by default (employees)
 * and Rs. 11 (contractors); workers can have their own rate, per item, from a date.
 */
beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->day = Carbon::parse('2026-11-05');

    $this->cut = Stage::query()->firstOrCreate(['name' => 'Cutting'], ['sort_order' => 1]);
    $this->stitch = Stage::query()->firstOrCreate(['name' => 'Stitching'], ['sort_order' => 2]);
    $this->glove = Item::factory()->finished()->create(['code' => 'GLV']);
    $this->ball = Item::factory()->finished()->create(['code' => 'BAL']);
    foreach ([$this->glove, $this->ball] as $item) {
        $route = ProductionRoute::create(['item_id' => $item->id, 'name' => 'Standard', 'is_default' => true]);
        $route->stages()->create(['stage_id' => $this->cut->id, 'sequence' => 1]);
        $route->stages()->create(['stage_id' => $this->stitch->id, 'sequence' => 2, 'wage_rate' => '10', 'contractor_rate' => '11']);
        $item->setRelation('stdRoute', $route);
        $bom = app(BomService::class)->newVersion($item);
        $bom->lines()->create(['component_item_id' => Item::factory()->rawMaterial('pcs')->create()->id, 'quantity' => '1', 'unit_id' => Unit::where('symbol', 'pcs')->value('id'), 'wastage_percent' => 0]);
        approver(BomService::class)->approve($bom);
    }

    $this->workerA = Employee::factory()->create(['employee_type' => Employee::TYPE_WAGE, 'name' => 'Worker A']);
    $this->workerB = Contractor::factory()->create(['name' => 'Worker B']);
    $this->workerC = Contractor::factory()->create(['name' => 'Worker C']);
    $this->cutter = Employee::factory()->create(['employee_type' => Employee::TYPE_SALARY]);
});

function ratedPtc(Item $item, string $qty): Ptc
{
    $ptc = app(Ptcs::class)->save(null, ['item_variant_id' => $item->variants()->first()->id, 'planned_qty' => $qty, 'route_id' => $item->stdRoute->id, 'start_date' => '2026-11-01']);

    return approver(Ptcs::class)->approve($ptc);
}

/** Cuts the pieces, then has $worker stitch them; returns the PTC. */
function stitchBy(Item $item, Worker $worker, string $qty, Carbon $day): Ptc
{
    $ptc = ratedPtc($item, $qty);
    $tx = app(PtcTransactions::class);
    $stages = $ptc->stages()->get()->keyBy('stage_id');
    $cutter = Worker::from('employee', test()->cutter->id, null);
    $tx->issueStage($ptc, $stages[test()->cut->id], $qty, $cutter, $day);
    $tx->receiveStage($ptc, $stages[test()->cut->id], $cutter, $qty, '0', $day);
    $tx->issueStage($ptc, $stages[test()->stitch->id], $qty, $worker, $day);
    $tx->receiveStage($ptc, $stages[test()->stitch->id], $worker, $qty, '0', $day);

    return $ptc;
}

function rate(array $attributes): WorkerRate
{
    return WorkerRate::query()->create($attributes + ['stage_id' => test()->stitch->id, 'effective_from' => '2026-11-01', 'is_active' => true]);
}

/** Contractor piece work is owed once approved (by someone other than the recorder). */
function contractorCost(Ptc $ptc): string
{
    WageWork::query()->where('ptc_id', $ptc->id)->whereNotNull('contractor_id')->where('status', 'pending')->get()
        ->each(fn (WageWork $w) => approver(WageWorks::class)->approve($w));

    return (string) PtcCost::query()->where('ptc_id', $ptc->id)->where('category', 'contractor')->sum('amount');
}

it('pays the standard stage rate when a worker has no rate of their own', function () {
    stitchBy($this->glove, Worker::from('employee', $this->workerA->id, null), '50', $this->day);
    $ptc = stitchBy($this->glove, Worker::from('contractor', null, $this->workerB->id), '20', $this->day);

    expect(WageWork::query()->whereNotNull('employee_id')->sole())->rate->toBe('10.0000')->amount->toBe('500.00')
        ->and(contractorCost($ptc))->toBe('220.00');
});

it("pays each worker's individual rate: 500 pieces at Rs. 12 is Rs. 6,000", function () {
    rate(['worker_type' => 'contractor', 'contractor_id' => $this->workerB->id, 'rate' => '12']);
    rate(['worker_type' => 'contractor', 'contractor_id' => $this->workerC->id, 'rate' => '9']);

    $b = stitchBy($this->glove, Worker::from('contractor', null, $this->workerB->id), '500', $this->day);
    $c = stitchBy($this->glove, Worker::from('contractor', null, $this->workerC->id), '100', $this->day);
    stitchBy($this->glove, Worker::from('employee', $this->workerA->id, null), '40', $this->day);

    expect(contractorCost($b))->toBe('6000.00')
        ->and(contractorCost($c))->toBe('900.00')
        ->and(WageWork::query()->whereNotNull('employee_id')->sole()->amount)->toBe('400.00');   // Worker A: standard Rs. 10
});

it('prefers an item-specific rate, uses the rate in force on the work date, and ignores inactive rates', function () {
    $a = Worker::from('employee', $this->workerA->id, null);
    rate(['worker_type' => 'employee', 'employee_id' => $this->workerA->id, 'rate' => '13']);
    rate(['worker_type' => 'employee', 'employee_id' => $this->workerA->id, 'rate' => '15', 'item_id' => $this->ball->id]);
    rate(['worker_type' => 'employee', 'employee_id' => $this->workerA->id, 'rate' => '14', 'effective_from' => '2026-11-20']);
    rate(['worker_type' => 'employee', 'employee_id' => $this->workerA->id, 'rate' => '99', 'effective_from' => '2026-11-04', 'is_active' => false]);

    stitchBy($this->glove, $a, '10', $this->day);                  // all-items rate on 5 Nov: 13
    stitchBy($this->ball, $a, '10', $this->day);                   // ball-specific rate: 15
    stitchBy($this->glove, $a, '10', Carbon::parse('2026-11-25')); // revised from 20 Nov: 14

    expect(WageWork::query()->orderBy('id')->pluck('rate')->all())->toBe(['13.0000', '15.0000', '14.0000']);
});

it('keeps approved work at the rate it was paid when the rate is revised later', function () {
    $r = rate(['worker_type' => 'employee', 'employee_id' => $this->workerA->id, 'rate' => '12']);
    stitchBy($this->glove, Worker::from('employee', $this->workerA->id, null), '100', $this->day);
    approver(WageWorks::class)->approve(WageWork::query()->sole());

    $r->update(['rate' => '20']);

    expect(WageWork::query()->sole())->rate->toBe('12.0000')->amount->toBe('1200.00')->status->toBe('approved');
});

it('manages rates on the screen with validation and permissions', function () {
    Livewire::test(RatesScreen::class)
        ->call('create')
        ->set('worker_type', 'employee')->set('employee_id', $this->cutter->id)->set('stage_id', $this->stitch->id)->set('rate', '12')
        ->call('save')->assertHasErrors(['employee_id'])                         // salaried staff are not paid by piece
        ->set('employee_id', $this->workerA->id)->call('save')->assertHasNoErrors();

    expect(WorkerRate::query()->sole())->rate->toBe('12.0000')->employee_id->toBe($this->workerA->id)->contractor_id->toBeNull();

    Livewire::test(RatesScreen::class)->call('create')
        ->set('worker_type', 'employee')->set('employee_id', $this->workerA->id)->set('stage_id', $this->stitch->id)->set('rate', '13')
        ->call('save')->assertHasErrors(['effective_from']);                     // same worker, stage, item and date

    Livewire::test(RatesScreen::class)->assertSee('Worker A')->call('export', 'csv')->assertFileDownloaded();

    $this->actingAs(userWith(['worker_rates.view']));
    $this->get(route('production.worker-rates'))->assertOk();
    Livewire::test(RatesScreen::class)->call('create')->assertForbidden();
    $this->actingAs(userWith([]));
    $this->get(route('production.worker-rates'))->assertForbidden();
});

it('shows piece rates only to people who may see pay, and deactivates instead of deleting', function () {
    $rate = rate(['worker_type' => 'employee', 'employee_id' => $this->workerA->id, 'rate' => '12.75']);

    // A supervisor with the screen but without salary.view sees who has a rate, not the amount.
    $this->actingAs(userWith(['worker_rates.view', 'worker_rates.create', 'worker_rates.edit', 'worker_rates.export']));
    Livewire::test(RatesScreen::class)->assertSee('Worker A')->assertDontSee('12.75')
        ->call('create')->assertForbidden();
    Livewire::test(RatesScreen::class)->call('edit', $rate->id)->assertForbidden();
    Livewire::test(RatesScreen::class)->call('export', 'csv')->assertFileDownloaded();
    $screen = new RatesScreen;
    expect(array_keys((fn () => $this->exportColumns())->call($screen)))->not->toContain('Rate');

    $this->actingAs(userWith(['worker_rates.view', 'worker_rates.delete', 'salary.view']));
    Livewire::test(RatesScreen::class)->assertSee('12.75')->call('deactivate', $rate->id);
    expect($rate->fresh())->not->toBeNull()->is_active->toBeFalse();
});
