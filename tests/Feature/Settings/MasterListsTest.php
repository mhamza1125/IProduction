<?php

use App\Modules\Core\Database\Seeders\CoreSeeder;
use App\Modules\Core\Livewire\Lookups\Index as Lookups;
use App\Modules\Core\Livewire\Settings\Payroll;
use App\Modules\Core\Livewire\Stages\Index as Stages;
use App\Modules\Core\Livewire\Units\Index as Units;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Modules\Core\Models\UnitConversion;
use App\Modules\Core\Services\Settings;
use App\Modules\Finance\Database\Seeders\ChartOfAccountsSeeder;
use App\Modules\Finance\Models\GlAccount;
use App\Support\Documents\DocumentException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([CoreSeeder::class, ChartOfAccountsSeeder::class]);
    $this->actingAs(admin());
});

it('seeds the default units, stages and conversions', function () {
    expect(Unit::pluck('name')->all())->toContain('Piece', 'Pair', 'Dozen', 'Kilogram', 'Gram', 'Sheet', 'Roll', 'Metre', 'Foot', 'Litre', 'Millilitre', 'Box')
        ->and(Unit::where('symbol', 'pcs')->first()->allows_decimal)->toBeFalse()
        ->and(Stage::ordered()->pluck('name')->all())->toBe(['Stitching', 'Stamping', 'Leather Attach', 'Logo Embossing', 'Magzi'])
        ->and(UnitConversion::count())->toBe(2);
});

it('manages units and conversions', function () {
    Livewire::test(Units::class)
        ->call('createUnit')->set('name', 'Bundle')->set('symbol', 'bdl')->set('allows_decimal', false)->call('saveUnit')
        ->assertHasNoErrors();

    $bundle = Unit::where('symbol', 'bdl')->firstOrFail();
    $piece = Unit::where('symbol', 'pcs')->firstOrFail();

    Livewire::test(Units::class)
        ->call('createConversion')->set('from_unit_id', $bundle->id)->set('to_unit_id', $piece->id)->set('factor', '50')
        ->call('saveConversion')->assertHasNoErrors();

    expect(UnitConversion::where('from_unit_id', $bundle->id)->value('factor'))->toBe('50.000000');

    Livewire::test(Units::class)
        ->call('createConversion')->set('from_unit_id', $bundle->id)->set('to_unit_id', $bundle->id)->set('factor', '0')
        ->call('saveConversion')->assertHasErrors(['to_unit_id', 'factor']);
});

it('manages stages and lookups', function () {
    Livewire::test(Stages::class)->call('create')->set('name', 'Packing')->call('save')->assertHasNoErrors();
    expect(Stage::where('name', 'Packing')->value('sort_order'))->toBe(60);

    Livewire::test(Stages::class)->call('create')->set('name', 'Stitching')->call('save')->assertHasErrors(['name' => 'unique']);

    Livewire::test(Lookups::class)->set('type', 'department')->call('create')->set('name', 'Cutting')->call('save')->assertHasNoErrors();
    expect(Lookup::options('department'))->toContain('Cutting');

    // The same name may exist in another list.
    Livewire::test(Lookups::class)->set('type', 'designation')->call('create')->set('name', 'Cutting')->call('save')->assertHasNoErrors();
});

it('blocks deleting a master record that is in use', function () {
    $stage = Stage::firstOrFail();
    Stage::whenUsed(fn (Stage $s) => $s->is($stage));

    expect(fn () => $stage->delete())->toThrow(DocumentException::class, 'can only be deactivated');

    $stage->update(['is_active' => false]);
    expect($stage->fresh()->is_active)->toBeFalse();
});

it('saves payroll settings with the specification defaults', function () {
    $settings = app(Settings::class);
    expect((int) $settings->get('payroll.salary_day_basis', 30))->toBe(30);

    Livewire::test(Payroll::class)
        ->assertSet('salary_day_basis', 30)->assertSet('daily_hours', '8')
        ->set('salary_day_basis', 26)->set('deduct_late_hours', false)->call('save')->assertHasNoErrors();

    $settings->flush();
    expect($settings->get('payroll.salary_day_basis'))->toBe(26)->and($settings->get('payroll.deduct_late_hours'))->toBeFalse();
});

it('seeds the default chart of accounts and protects system accounts', function () {
    expect(GlAccount::count())->toBe(count(ChartOfAccountsSeeder::ACCOUNTS))
        ->and(GlAccount::system('accounts_receivable')->type)->toBe('asset')
        ->and(GlAccount::system('currency_exchange_clearing')->type)->toBe('clearing')
        ->and(GlAccount::system('opening_balance')->type)->toBe('equity');

    $ar = GlAccount::system('accounts_receivable');
    expect(fn () => $ar->delete())->toThrow(DocumentException::class)
        ->and(fn () => $ar->update(['type' => 'income']))->toThrow(DocumentException::class);

    // Users can add their own accounts, e.g. a new expense head.
    $custom = GlAccount::create(['code' => '6700', 'name' => 'Repairs', 'type' => 'expense']);
    $custom->delete();
    expect(GlAccount::where('code', '6700')->exists())->toBeFalse();
});
