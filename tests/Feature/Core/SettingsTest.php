<?php

use App\Modules\Core\Livewire\Settings\Company;
use App\Modules\Core\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('returns defaults for settings that were never saved', function () {
    $settings = app(Settings::class);

    expect($settings->get('base_currency'))->toBe('PKR')
        ->and($settings->get('fiscal_year.start_month'))->toBe(7)
        ->and($settings->get('unknown.key', 'fallback'))->toBe('fallback');
});

it('saves company settings and resizes the logo', function () {
    Storage::fake('public');
    $this->actingAs(admin());

    Livewire::test(Company::class)
        ->set('name', 'Acme Textiles (Pvt) Ltd')
        ->set('ntn', '1234567-8')
        ->set('fiscal_year_start_month', 1)
        ->set('logo', UploadedFile::fake()->image('logo.png', 1200, 400))
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(Settings::class);
    $settings->flush();

    expect($settings->get('company.name'))->toBe('Acme Textiles (Pvt) Ltd')
        ->and($settings->get('company.ntn'))->toBe('1234567-8')
        ->and($settings->get('fiscal_year.start_month'))->toBe(1);

    $path = $settings->get('company.logo_path');
    Storage::disk('public')->assertExists($path);
    expect(getimagesizefromstring(Storage::disk('public')->get($path))[0])->toBe(600);

    $this->get('/dashboard')->assertSee('Acme Textiles (Pvt) Ltd');
});

it('validates company settings', function () {
    $this->actingAs(admin());

    Livewire::test(Company::class)
        ->set('name', '')
        ->set('email', 'not-an-email')
        ->set('fiscal_year_start_month', 13)
        ->call('save')
        ->assertHasErrors(['name', 'email', 'fiscal_year_start_month']);
});
