<?php

use App\Modules\Catalog\Livewire\Attributes\Index;
use App\Modules\Catalog\Livewire\Boms\Edit as BomEdit;
use App\Modules\Catalog\Livewire\Items;
use App\Modules\Catalog\Livewire\Routes\Form as RouteForm;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\Bom;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Services\BomExplosion;
use App\Modules\Catalog\Services\BomService;
use App\Modules\Catalog\Services\VariantGenerator;
use App\Modules\Core\Database\Seeders\CoreSeeder;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Support\Documents\DocumentException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(CoreSeeder::class);
    $this->actingAs(admin());
});

function unitId(string $symbol): int
{
    return Unit::where('symbol', $symbol)->value('id');
}

/** Color: Red, Blue; Size: S, M, L. */
function colorsAndSizes(): array
{
    $color = Attribute::create(['name' => 'Color']);
    $size = Attribute::create(['name' => 'Size']);
    $red = $color->values()->create(['value' => 'Red']);
    $blue = $color->values()->create(['value' => 'Blue']);
    $s = $size->values()->create(['value' => 'S']);
    $m = $size->values()->create(['value' => 'M']);
    $l = $size->values()->create(['value' => 'L']);

    return compact('color', 'size', 'red', 'blue', 's', 'm', 'l');
}

/**
 * Approved BOM helper. $lines: [[component Item, qty, unit symbol, wastage %, variant?]]
 */
function approvedBom(Item $item, array $lines, string $output = '1'): Bom
{
    $bom = app(BomService::class)->newVersion($item);
    $bom->update(['output_quantity' => $output]);
    foreach ($lines as $i => [$component, $qty, $unit, $wastage]) {
        $bom->lines()->create([
            'component_item_id' => $component->id,
            'component_variant_id' => $lines[$i][4] ?? null,
            'quantity' => $qty,
            'unit_id' => unitId($unit),
            'wastage_percent' => $wastage,
            'sort_order' => $i,
        ]);
    }

    return app(BomService::class)->approve($bom);
}

it('gives every item a variant and numbers it automatically', function () {
    Livewire::test(Items\Form::class)
        ->set('name', 'Leather Glove')->set('type', Item::FINISHED_GOOD)
        ->call('save')->assertHasNoErrors();

    $item = Item::firstOrFail();
    expect($item->code)->toBe('ITM-0001')
        ->and($item->can_make)->toBeTrue()->and($item->can_sell)->toBeTrue()->and($item->can_buy)->toBeFalse()
        ->and($item->variants()->count())->toBe(1)
        ->and($item->variants()->first()->sku)->toBe('ITM-0001');
});

it('requires an item to be bought, made or both', function () {
    Livewire::test(Items\Form::class)
        ->set('name', 'Nothing')->set('can_buy', false)->set('can_make', false)
        ->call('save')->assertHasErrors('can_buy');
});

it('generates every attribute combination and replaces the plain variant', function () {
    $a = colorsAndSizes();
    $glove = Item::factory()->finished()->create(['code' => 'GLV']);

    $created = app(VariantGenerator::class)->generate($glove, [
        $a['color']->id => [$a['red']->id, $a['blue']->id],
        $a['size']->id => [$a['s']->id, $a['m']->id, $a['l']->id],
    ]);

    expect($created)->toBe(6)
        ->and($glove->variants()->count())->toBe(6)
        ->and($glove->variants()->pluck('sku')->all())->toContain('GLV-RED-M', 'GLV-BLUE-L')
        ->and($glove->variants()->where('sku', 'GLV-RED-M')->first()->label)->toBe('Red / M');

    // Generating again creates nothing new; SKUs stay unique.
    expect(app(VariantGenerator::class)->generate($glove, [$a['color']->id => [$a['red']->id], $a['size']->id => [$a['m']->id]]))->toBe(0);
});

it('lets users remove unwanted variants but not used ones, and keeps at least one', function () {
    $a = colorsAndSizes();
    $glove = Item::factory()->finished()->create(['code' => 'GLV']);
    app(VariantGenerator::class)->generate($glove, [$a['color']->id => [$a['red']->id, $a['blue']->id]]);

    $red = $glove->variants()->where('label', 'Red')->first();
    $blue = $glove->variants()->where('label', 'Blue')->first();
    markUsedWhen(ItemVariant::class, fn (ItemVariant $v) => $v->is($red));

    Livewire::test(Items\Variants::class, ['item' => $glove])->call('remove', $red->id);
    expect(ItemVariant::find($red->id))->not->toBeNull();

    Livewire::test(Items\Variants::class, ['item' => $glove])->call('remove', $blue->id);
    expect(ItemVariant::withTrashed()->find($blue->id))->toBeNull();

    Livewire::test(Items\Variants::class, ['item' => $glove])->call('edit', $red->id)->set('is_active', false)->call('save')->assertHasErrors('is_active');
});

it('keeps the stock unit fixed after creation', function () {
    $item = Item::factory()->rawMaterial('kg')->create();

    Livewire::test(Items\Form::class, ['item' => $item])->set('stock_unit_id', unitId('pcs'))->call('save')->assertHasNoErrors();

    expect($item->fresh()->stock_unit_id)->toBe(unitId('kg'));
});

it('saves routes with ordered unique stages and one default per item', function () {
    $glove = Item::factory()->finished()->create();
    $stages = Stage::ordered()->pluck('id')->all();

    $lines = fn (array $ids) => array_map(fn ($id) => ['stage_id' => $id, 'is_optional' => false, 'default_work_type' => 'employee', 'wage_rate' => '12.5', 'contractor_rate' => '', 'machine' => '', 'instructions' => ''], $ids);

    Livewire::test(RouteForm::class)->set('item_id', $glove->id)->set('name', 'Standard')->set('lines', $lines([$stages[0], $stages[0]]))
        ->call('save')->assertHasErrors('lines.1.stage_id');

    Livewire::test(RouteForm::class)->set('item_id', $glove->id)->set('name', 'Standard')->set('lines', $lines($stages))->call('save')->assertHasNoErrors();
    Livewire::test(RouteForm::class)->set('item_id', $glove->id)->set('name', 'Short')->set('is_default', true)->set('lines', $lines([$stages[0], $stages[4]]))->call('save')->assertHasNoErrors();

    expect(ProductionRoute::where('item_id', $glove->id)->where('is_default', true)->pluck('name')->all())->toBe(['Short'])
        ->and(ProductionRoute::where('name', 'Standard')->first()->stages()->with('stage')->get()->pluck('stage.name')->all())
        ->toBe(['Stitching', 'Stamping', 'Leather Attach', 'Logo Embossing', 'Magzi'])
        ->and(ProductionRoute::where('name', 'Standard')->first()->stages->first()->wage_rate)->toBe('12.5000');
});

it('calculates requirements with wastage, output quantity and unit conversion', function () {
    $glove = Item::factory()->finished()->create();
    $leather = Item::factory()->rawMaterial('kg')->create(['code' => 'LEATHER']);
    $thread = Item::factory()->rawMaterial('m')->create(['code' => 'THREAD']);
    $card = Item::factory()->rawMaterial('pcs')->create(['code' => 'CARD']);

    // For 12 gloves: 1,800 g leather (+10%), 30 m thread (+5%), 12 cards (+1%).
    approvedBom($glove, [
        [$leather, '1800', 'g', '10'],
        [$thread, '30', 'm', '5'],
        [$card, '12', 'pcs', '1'],
    ], output: '12');

    $rows = collect(app(BomExplosion::class)->explode($glove->variants()->first(), '80'))
        ->mapWithKeys(fn ($r) => [$r['variant']->item->code => $r['quantity']]);

    // 80 × 1800/12 × 1.10 = 13,200 g = 13.2 kg
    expect($rows['LEATHER'])->toBe('13.2000')
        // 80 × 30/12 × 1.05 = 210 m
        ->and($rows['THREAD'])->toBe('210.0000')
        // 80 × 12/12 × 1.01 = 80.8 → whole pieces are rounded up to 81
        ->and($rows['CARD'])->toBe('81.0000');
});

it('applies attribute and variant overrides, variant-specific winning', function () {
    $a = colorsAndSizes();
    $glove = Item::factory()->finished()->create(['code' => 'GLV']);
    app(VariantGenerator::class)->generate($glove, [$a['color']->id => [$a['red']->id, $a['blue']->id], $a['size']->id => [$a['m']->id, $a['l']->id]]);

    $leather = Item::factory()->rawMaterial('kg')->create(['code' => 'LEATHER']);
    $thread = Item::factory()->rawMaterial('m')->create(['code' => 'THREAD']);
    app(VariantGenerator::class)->generate($thread, [$a['color']->id => [$a['red']->id, $a['blue']->id]]);
    $redThread = $thread->variants()->where('label', 'Red')->first();
    $blueThread = $thread->variants()->where('label', 'Blue')->first();

    $bom = app(BomService::class)->newVersion($glove);
    $leatherLine = $bom->lines()->create(['component_item_id' => $leather->id, 'quantity' => '0.15', 'unit_id' => unitId('kg'), 'wastage_percent' => 0]);
    $threadLine = $bom->lines()->create(['component_item_id' => $thread->id, 'component_variant_id' => $blueThread->id, 'quantity' => '2', 'unit_id' => unitId('m'), 'wastage_percent' => 0]);

    $redLarge = $glove->variants()->where('label', 'Red / L')->first();

    // More leather for every Large; even more for Red / L specifically; red thread for Red.
    $bom->overrides()->create(['match_attribute_value_id' => $a['l']->id, 'bom_line_id' => $leatherLine->id, 'quantity' => '0.18']);
    $bom->overrides()->create(['match_variant_id' => $redLarge->id, 'bom_line_id' => $leatherLine->id, 'quantity' => '0.2']);
    $bom->overrides()->create(['match_attribute_value_id' => $a['red']->id, 'bom_line_id' => $threadLine->id, 'component_variant_id' => $redThread->id]);
    app(BomService::class)->approve($bom);

    $req = fn (string $label) => collect(app(BomExplosion::class)->explode($glove->variants()->where('label', $label)->first(), '10'))
        ->mapWithKeys(fn ($r) => [$r['variant']->sku => $r['quantity']])->all();

    expect($req('Blue / M'))->toBe(['LEATHER' => '1.5000', $blueThread->sku => '20.0000'])
        ->and($req('Blue / L'))->toBe(['LEATHER' => '1.8000', $blueThread->sku => '20.0000'])
        ->and($req('Red / L'))->toBe(['LEATHER' => '2.0000', $redThread->sku => '20.0000']);
});

it('explodes multi-level BOMs', function () {
    $glove = Item::factory()->finished()->create(['code' => 'GLOVE']);
    $lining = Item::factory()->create(['code' => 'LINING', 'type' => Item::SEMI_FINISHED, 'can_make' => true]);
    $fabric = Item::factory()->rawMaterial('m')->create(['code' => 'FABRIC']);

    approvedBom($lining, [[$fabric, '0.5', 'm', '0']]);
    approvedBom($glove, [[$lining, '2', 'pcs', '0']]);

    $rows = app(BomExplosion::class)->explode($glove->variants()->first(), '10');

    expect(array_map(fn ($r) => [$r['level'], $r['variant']->item->code, $r['quantity'], $r['has_bom']], $rows))->toBe([
        [1, 'LINING', '20.0000', true],
        [2, 'FABRIC', '10.0000', false],
    ]);
});

it('rejects circular BOMs', function () {
    $a = Item::factory()->create(['code' => 'A', 'can_make' => true, 'type' => Item::SEMI_FINISHED]);
    $b = Item::factory()->create(['code' => 'B', 'can_make' => true, 'type' => Item::SEMI_FINISHED]);
    $c = Item::factory()->create(['code' => 'C', 'can_make' => true, 'type' => Item::SEMI_FINISHED]);

    approvedBom($a, [[$b, '1', 'pcs', '0']]);
    approvedBom($b, [[$c, '1', 'pcs', '0']]);

    $bom = app(BomService::class)->newVersion($c);
    $bom->lines()->create(['component_item_id' => $a->id, 'quantity' => '1', 'unit_id' => unitId('pcs'), 'wastage_percent' => 0]);

    expect(fn () => app(BomService::class)->approve($bom))->toThrow(DocumentException::class, 'Circular BOM: C → A → B → C');

    Livewire::test(BomEdit::class, ['bom' => $bom])->call('save')->assertHasErrors('lines');
});

it('versions BOMs: approved versions are locked and new versions copy the last one', function () {
    $glove = Item::factory()->finished()->create();
    $leather = Item::factory()->rawMaterial('kg')->create();
    $v1 = approvedBom($glove, [[$leather, '0.15', 'kg', '5']]);

    expect(fn () => $v1->update(['output_quantity' => '2']))->toThrow(DocumentException::class)
        ->and(fn () => $v1->lines()->first()->update(['quantity' => '1']))->toThrow(DocumentException::class);

    $v2 = app(BomService::class)->newVersion($glove);
    expect($v2->version)->toBe(2)->and($v2->lines()->count())->toBe(1)->and($v2->lines()->first()->wastage_percent)->toBe('5.00');

    expect(fn () => app(BomService::class)->newVersion($glove))->toThrow(DocumentException::class, 'draft');

    $v2->lines()->first()->update(['quantity' => '0.16']);
    app(BomService::class)->approve($v2);

    expect($v1->fresh())->status->toBe(Bom::SUPERSEDED)->is_active->toBeFalse()
        ->and($glove->activeBom()->version)->toBe(2);
});

it('asks for a variant when a component has several', function () {
    $a = colorsAndSizes();
    $glove = Item::factory()->finished()->create();
    $thread = Item::factory()->rawMaterial('m')->create(['code' => 'THREAD']);
    app(VariantGenerator::class)->generate($thread, [$a['color']->id => [$a['red']->id, $a['blue']->id]]);
    approvedBom($glove, [[$thread, '2', 'm', '0']]);

    app(BomExplosion::class)->explode($glove->variants()->first(), '1');
})->throws(DocumentException::class, 'several variants');

it('protects catalog pages and cost fields', function () {
    $item = Item::factory()->create(['default_purchase_cost' => '123.4567']);

    $this->actingAs(userWith(['items.view']));
    $this->get(route('catalog.items.index'))->assertOk();
    $this->get(route('catalog.items.show', $item))->assertOk()->assertDontSee('123.4567');
    $this->get(route('catalog.items.create'))->assertForbidden();
    $this->get(route('catalog.boms.index'))->assertForbidden();

    $this->actingAs(userWith(['items.view', 'cost.view']));
    $this->get(route('catalog.items.show', $item))->assertSee('123.4567');
});

it('renders every catalog page', function () {
    $glove = Item::factory()->finished()->create();
    $leather = Item::factory()->rawMaterial('kg')->create();
    $bom = approvedBom($glove, [[$leather, '0.15', 'kg', '5']]);
    $route = ProductionRoute::create(['item_id' => $glove->id, 'name' => 'Standard', 'is_default' => true]);

    foreach ([
        route('catalog.items.index'), route('catalog.items.create'), route('catalog.items.show', $glove), route('catalog.items.edit', $glove),
        route('catalog.variants.index'), route('catalog.attributes.index'), route('catalog.routes.index'), route('catalog.routes.create'),
        route('catalog.routes.edit', $route), route('catalog.boms.index'), route('catalog.boms.edit', $bom),
    ] as $url) {
        $this->get($url)->assertOk();
    }

    Livewire::test(BomEdit::class, ['bom' => $bom])->set('preview_quantity', '100')->call('explode')->assertSee('15.75 kg')->assertSee('+5');
});

it('lists attributes and their values on the attributes page', function () {
    $a = colorsAndSizes();

    Livewire::test(Index::class)
        ->assertSee('Color')->assertSee('Red')->assertSee('Size')
        ->set('newValue.'.$a['color']->id, 'Green')->call('addValue', $a['color']->id)
        ->assertSee('Green');
});
