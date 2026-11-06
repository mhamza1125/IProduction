<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Lookup;
use App\Modules\Inventory\Actions\PostStockAdjustment;
use App\Modules\Inventory\Livewire\Adjustments;
use App\Modules\Inventory\Livewire\Stock\Index;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\StockLedger;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Livewire\Livewire;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->variant = Item::factory()->rawMaterial('kg')->create(['code' => 'LEATHER', 'default_purchase_cost' => '1500'])->variants()->first();
    $this->reason = Lookup::where('type', 'adjustment_reason')->first();
});

function makeAdjustment(array $lines): StockAdjustment
{
    $component = Livewire::test(Adjustments\Form::class)->set('reason_id', test()->reason->id)->set('lines', array_map(fn ($l) => $l + [
        'item_variant_id' => test()->variant->id, 'stage_id' => null, 'status' => 'available',
        'location_id' => Location::mainStore()->id, 'lot_id' => null, 'unit_cost' => '', 'notes' => '',
    ], $lines))->call('save')->assertHasNoErrors();

    return StockAdjustment::latest('id')->firstOrFail();
}

it('adds stock through Draft, Approved and Posted with a new Adjustment lot', function () {
    $adj = makeAdjustment([['quantity' => '12.5', 'unit_cost' => '1450']]);
    expect($adj->number)->toBe('ADJ-26-0001')->and($adj->status)->toBe('draft');

    app(DocumentWorkflow::class)->approve($adj);
    app(PostStockAdjustment::class)($adj);

    $lot = Lot::firstOrFail();
    expect($adj->fresh()->isPosted())->toBeTrue()
        ->and($lot->origin)->toBe('adjustment')->and($lot->unit_cost)->toBe('1450.0000')
        ->and(app(StockLedger::class)->onHand($this->variant->id))->toBe('12.5000');

    // Posted documents cannot be edited or posted again.
    expect(fn () => $adj->update(['notes' => 'x']))->toThrow(DocumentException::class)
        ->and(fn () => app(PostStockAdjustment::class)($adj))->toThrow(DocumentException::class, 'already posted');
});

it('defaults the unit cost from the item when none is given', function () {
    $adj = makeAdjustment([['quantity' => '1']]);
    expect($adj->lines()->first()->unit_cost)->toBe('1500.0000');
});

it('rolls back every line when one line is short', function () {
    $in = makeAdjustment([['quantity' => '10', 'unit_cost' => '100']]);
    app(DocumentWorkflow::class)->approve($in);
    app(PostStockAdjustment::class)($in);

    $other = Item::factory()->create()->variants()->first();
    $adj = makeAdjustment([['quantity' => '-3'], ['quantity' => '-1', 'item_variant_id' => $other->id]]);
    app(DocumentWorkflow::class)->approve($adj);

    expect(fn () => app(PostStockAdjustment::class)($adj))->toThrow(DocumentException::class, 'Not enough');

    expect($adj->fresh()->isPosted())->toBeFalse()
        ->and(app(StockLedger::class)->onHand($this->variant->id))->toBe('10.0000');
});

it('turns a physical count into a draft adjustment of the differences', function () {
    $in = makeAdjustment([['quantity' => '10', 'unit_cost' => '100']]);
    app(DocumentWorkflow::class)->approve($in);
    app(PostStockAdjustment::class)($in);
    $balance = StockBalance::firstOrFail();

    Livewire::test(Adjustments\Count::class)
        ->set('counted', [$balance->id => '8.5'])
        ->set('reason_id', $this->reason->id)
        ->call('createAdjustment')
        ->assertHasNoErrors();

    $count = StockAdjustment::where('from_count', true)->firstOrFail();
    expect($count->lines()->first())->quantity->toBe('-1.5000')->system_quantity->toBe('10.0000')->counted_quantity->toBe('8.5000')->lot_id->toBe($balance->lot_id);

    app(DocumentWorkflow::class)->approve($count);
    app(PostStockAdjustment::class)($count);
    expect(app(StockLedger::class)->onHand($this->variant->id))->toBe('8.5000');
});

it('enforces adjustment permissions: store staff draft, managers approve and post', function () {
    $this->actingAs(userWith(['stock_adjustments.view', 'stock_adjustments.create', 'stock_adjustments.edit']));
    $adj = makeAdjustment([['quantity' => '1', 'unit_cost' => '1']]);

    Livewire::test(Adjustments\Show::class, ['adjustment' => $adj])->call('approveDocument')->assertForbidden();
    Livewire::test(Adjustments\Show::class, ['adjustment' => $adj])->call('postDocumentAction')->assertForbidden();

    $this->actingAs(userWith(['stock_adjustments.view', 'stock_adjustments.approve', 'stock_adjustments.post']));
    Livewire::test(Adjustments\Show::class, ['adjustment' => $adj])->call('approveDocument');
    Livewire::test(Adjustments\Show::class, ['adjustment' => $adj])->call('postDocumentAction');

    expect($adj->fresh()->isPosted())->toBeTrue();
});

it('renders every inventory page', function () {
    $adj = makeAdjustment([['quantity' => '3', 'unit_cost' => '10']]);
    app(DocumentWorkflow::class)->approve($adj);
    app(PostStockAdjustment::class)($adj);
    $lot = Lot::firstOrFail();

    foreach ([
        route('inventory.stock'), route('inventory.stock', ['view' => 'stages']), route('inventory.stock', ['view' => 'locations']),
        route('inventory.movements'), route('inventory.lots.index'), route('inventory.lots.show', $lot), route('inventory.reservations'),
        route('inventory.locations'), route('inventory.adjustments.index'), route('inventory.adjustments.create'),
        route('inventory.adjustments.count'), route('inventory.adjustments.show', $adj),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    Livewire::test(Index::class)->assertSee('LEATHER')->call('export', 'csv')->assertFileDownloaded();
});
