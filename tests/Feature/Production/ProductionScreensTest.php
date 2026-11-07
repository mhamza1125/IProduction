<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Core\Models\Stage;
use App\Modules\Inventory\Models\Location;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Livewire\Jobs;
use App\Modules\Production\Livewire\Processes;
use App\Modules\Production\Livewire\Ptcs;
use App\Modules\Production\Livewire\WageWork;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\Rejection;
use App\Modules\Production\Services\ContractorJobs;
use App\Modules\Production\Services\Ptcs as PtcService;
use App\Modules\Production\Services\PtcTransactions;
use App\Modules\Production\Services\Rejections;
use App\Modules\Production\Services\Worker;
use App\Support\Documents\DocumentException;
use Carbon\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->stage = Stage::query()->firstOrCreate(['name' => 'Cutting'], ['sort_order' => 1]);
    $this->item = Item::factory()->finished()->create(['code' => 'GLV']);
    $this->variant = $this->item->variants()->first();
    $this->route = ProductionRoute::create(['item_id' => $this->item->id, 'name' => 'Standard', 'is_default' => true]);
    $this->route->stages()->create(['stage_id' => $this->stage->id, 'sequence' => 1, 'wage_rate' => '5']);
    $this->employee = Employee::factory()->create(['employee_type' => Employee::TYPE_WAGE]);
});

function screenPtc(): Ptc
{
    $p = app(PtcService::class)->save(null, ['item_variant_id' => test()->variant->id, 'planned_qty' => '10', 'route_id' => test()->route->id, 'start_date' => '2026-11-05']);

    return app(PtcService::class)->approve($p);
}

it('creates a PTC from the form and records stage transactions from its screen', function () {
    Livewire::test(Ptcs\Form::class)
        ->set('item_variant_id', $this->variant->id)->set('planned_qty', '10')->set('route_id', $this->route->id)
        ->call('save')->assertHasNoErrors()->assertRedirect();
    $ptc = Ptc::query()->sole();

    Livewire::test(Ptcs\Show::class, ['ptc' => $ptc])->call('approve');
    $ptc->refresh();
    $stageId = $ptc->stages()->first()->id;

    Livewire::test(Ptcs\Show::class, ['ptc' => $ptc])
        ->set('type', 'stage_issue')->set('ptc_stage_id', $stageId)->set('worker_type', 'employee')->set('employee_id', $this->employee->id)->set('quantity', '10')
        ->call('record')->assertHasNoErrors()
        ->set('type', 'stage_receive')->set('ptc_stage_id', $stageId)->set('employee_id', $this->employee->id)->set('good', '9')->set('rejected', '1')->set('tx_reason', 'Torn')
        ->call('record')
        ->assertSee('Stage Receive');

    expect($ptc->fresh()->status)->toBe('in_progress')
        ->and(Rejection::query()->sole()->quantity)->toBe('1.0000');

    Livewire::test(WageWork\Index::class)->assertSee($this->employee->name)->call('approve', App\Modules\Production\Models\WageWork::query()->value('id'));
    expect(App\Modules\Production\Models\WageWork::query()->value('status'))->toBe('approved');
});

it('renders every production screen and print', function () {
    $ptc = screenPtc();
    app(PtcTransactions::class)->issueStage($ptc, $ptc->stages()->first(), '5', Worker::from('employee', $this->employee->id, null), Carbon::parse('2026-11-05'));
    $contractor = Contractor::factory()->create();
    $job = app(ContractorJobs::class)->approve(app(ContractorJobs::class)->save(null, ['contractor_id' => $contractor->id, 'job_date' => '2026-11-05'], [['item_variant_id' => $this->variant->id, 'stage_id' => null, 'quantity' => '10', 'rate' => '5']], []));
    $rejection = app(Rejections::class)->record(null, ['rejection_date' => '2026-11-05', 'item_variant_id' => $this->variant->id, 'lot_id' => $ptc->output_lot_id,
        'location_id' => Location::mainStore()->id, 'quantity' => '1', 'reason' => 'Test']);

    foreach ([
        route('production.ptcs.index'), route('production.ptcs.create'), route('production.ptcs.show', $ptc), route('production.ptcs.print', $ptc),
        route('production.jobs.index'), route('production.jobs.create'), route('production.jobs.show', $job), route('production.jobs.print', $job),
        route('production.processes.index'), route('production.processes.create'),
        route('production.rejections.index', ['status' => '']), route('production.rejections.show', $rejection), route('production.wage-work', ['status' => '']),
    ] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get(route('production.ptcs.show', $ptc))->assertSee($ptc->number)->assertSee('Stage board');
});

it('protects production screens and actions with permissions', function () {
    $ptc = screenPtc();
    $viewer = userWith(['ptcs.view']);

    $this->actingAs(userWith([]))->get(route('production.ptcs.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('production.ptcs.show', $ptc))->assertOk()->assertDontSee('Record a transaction');
    $this->actingAs($viewer)->get(route('production.ptcs.print', $ptc))->assertForbidden();
    $this->actingAs($viewer)->get(route('production.jobs.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('production.wage-work'))->assertForbidden();

    Livewire::actingAs($viewer)->test(Ptcs\Show::class, ['ptc' => $ptc])->set('type', 'release')->set('quantity', '1')->call('record')->assertForbidden();
    Livewire::actingAs($viewer)->test(Ptcs\Show::class, ['ptc' => $ptc])->call('status', 'complete')->assertForbidden();
    Livewire::actingAs(userWith(['wage_work.view']))->test(WageWork\Index::class)->call('approve', 1)->assertForbidden();
    Livewire::actingAs(userWith(['material_processes.create', 'material_processes.view']))->test(Processes\Form::class)->call('saveAndPost')->assertForbidden();
    Livewire::actingAs(userWith(['contractor_jobs.view']))->test(Jobs\Form::class)->call('save')->assertForbidden();
});

it('validates the PTC and contractor job forms', function () {
    Livewire::test(Ptcs\Form::class)->set('planned_qty', '0')->call('save')->assertHasErrors(['item_variant_id' => 'required', 'planned_qty', 'route_id' => 'required']);
    Livewire::test(Jobs\Form::class)->set('lines.0.quantity', '-1')->call('save')->assertHasErrors(['contractor_id', 'lines.0.item_variant_id', 'lines.0.quantity', 'lines.0.rate']);
    Livewire::test(Processes\Form::class)->call('save')->assertHasErrors(['process_name', 'inputs.0.item_variant_id', 'outputs.0.quantity']);

    // A raw material cannot be made.
    $raw = Item::factory()->rawMaterial()->create()->variants()->first();
    expect(fn () => app(PtcService::class)->save(null, ['item_variant_id' => $raw->id, 'planned_qty' => '1', 'route_id' => $this->route->id, 'start_date' => '2026-11-05']))
        ->toThrow(DocumentException::class, 'cannot be made');
});
