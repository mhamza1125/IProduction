<?php

use App\Modules\Core\Database\Seeders\CoreSeeder;
use App\Modules\Core\Livewire\Currencies\Index;
use App\Modules\Core\Models\Currency;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(CoreSeeder::class);
    $this->actingAs(superAdmin());
});

it('seeds PKR as the only base currency', function () {
    expect(Currency::base()->code)->toBe('PKR')
        ->and(Currency::where('is_base', true)->count())->toBe(1);
});

it('adds a currency and upper-cases the code', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('code', 'sar')
        ->set('name', 'Saudi Riyal')
        ->set('symbol', 'SR')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect(Currency::where('code', 'SAR')->exists())->toBeTrue();
});

it('rejects a duplicate code', function () {
    Livewire::test(Index::class)
        ->call('create')
        ->set('code', 'USD')
        ->set('name', 'Duplicate')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);
});

it('protects the base currency', function () {
    $pkr = Currency::base();

    Livewire::test(Index::class)->call('toggleActive', $pkr->id);
    expect($pkr->fresh()->is_active)->toBeTrue();

    Livewire::test(Index::class)
        ->call('edit', $pkr->id)
        ->set('code', 'XYZ')
        ->call('save')
        ->assertHasErrors('code');
    expect($pkr->fresh()->code)->toBe('PKR');
});
