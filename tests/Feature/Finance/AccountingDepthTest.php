<?php

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Core\Services\Settings;
use App\Modules\Finance\Livewire\Assets\Form as AssetForm;
use App\Modules\Finance\Livewire\Assets\Index as AssetList;
use App\Modules\Finance\Livewire\ChartOfAccounts\Index as ChartScreen;
use App\Modules\Finance\Models\Asset;
use App\Modules\Finance\Models\AssetDepreciation;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Services\Depreciation;
use App\Modules\Reports\Services\Reports;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** Depreciation, balance sheet classification and the cash flow statement, on the demo company. */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoDataSeeder::class);
    $this->actingAs(User::role(SyncPermissions::ADMIN)->firstOrFail());
    $this->dep = app(Depreciation::class);
});

function machine(array $extra = []): Asset
{
    return Asset::query()->create($extra + ['code' => 'AST-'.uniqid(), 'name' => 'Stitching machine', 'status' => 'in_use', 'value' => '120000',
        'useful_life_months' => 12, 'salvage_value' => null, 'depreciation_start' => '2026-09-01']);
}

function rowsOf(string $key, array $filters): Collection
{
    return app(Reports::class)->find($key)->rows($filters)->map(fn ($r) => (array) $r);
}

it('posts straight-line depreciation once per month and never beyond cost less salvage', function () {
    $asset = machine(['salvage_value' => '6000']);   // (120,000 − 6,000) ÷ 12 = 9,500 a month

    $journal = $this->dep->run(Carbon::parse('2026-09-01'));
    expect($journal)->not->toBeNull()->and($journal->journal_date->toDateString())->toBe('2026-09-30')
        ->and($this->dep->run(Carbon::parse('2026-09-15')))->toBeNull()               // same month again: nothing
        ->and(AssetDepreciation::query()->sole()->amount)->toBe('9500.00');

    $lines = DB::table('journal_lines as l')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')->where('l.journal_id', $journal->id)->pluck('l.debit', 'a.system_key');
    expect((string) $lines['depreciation'])->toBe('9500.00');

    // Run every month to the end of its life and beyond: it stops at 114,000.
    foreach (range(1, 14) as $m) {
        $this->dep->run(Carbon::parse('2026-09-01')->addMonthsNoOverflow($m));
    }
    expect($this->dep->accumulated($asset))->toBe('114000.00')->and($this->dep->bookValue($asset))->toBe('6000.00');
});

it('skips assets not in use or without a life, respects the period lock and needs permission', function () {
    machine(['status' => 'in_repair']);
    machine(['useful_life_months' => null]);
    expect($this->dep->due(Carbon::parse('2026-10-01')))->toBeEmpty();

    machine();
    app(Settings::class)->set('period_lock.date', '2026-10-31');
    expect(fn () => $this->dep->run(Carbon::parse('2026-10-01')))->toThrow(DocumentException::class);

    $this->actingAs(userWith(['assets.view', 'assets.edit']));
    Livewire::test(AssetList::class)->call('openRun')->assertForbidden();
});

it('runs from the asset register with a preview, and from the console', function () {
    machine();
    Livewire::test(AssetList::class)->call('openRun')->set('runMonth', '2026-09')->assertSee('10,000.00')
        ->call('runDepreciation')->assertHasNoErrors();
    expect(AssetDepreciation::query()->count())->toBe(1);

    $this->artisan('assets:depreciate', ['month' => '2026-10'])->expectsOutputToContain('posted as')->assertSuccessful();
    $this->artisan('assets:depreciate', ['--scheduled' => true])->expectsOutputToContain('Automatic depreciation is off')->assertSuccessful();
    expect(AssetDepreciation::query()->count())->toBe(2);
});

it('validates the asset form and locks cost once depreciation is posted', function () {
    $asset = machine();
    Livewire::test(AssetForm::class, ['asset' => $asset])->set('salvage_value', '200000')->call('save')->assertHasErrors(['salvage_value']);

    $this->dep->run(Carbon::parse('2026-09-01'));
    Livewire::test(AssetForm::class, ['asset' => $asset->fresh()])->set('value', '150000')->call('save')->assertHasErrors(['value'])
        ->assertSee('Book value');
});

it('groups the balance sheet into current and non-current, and it still balances', function () {
    machine();
    $this->dep->run(Carbon::parse('2026-09-01'));

    $rows = rowsOf('balance-sheet', ['to' => '2026-11-30', 'currency' => 'PKR']);
    $total = fn (string $line) => $rows->firstWhere('account', $line)['amount'];

    expect($rows->pluck('section')->unique()->values()->all())->toContain('Current assets', 'Non-current assets', 'Current liabilities', 'Equity')
        ->and($rows->firstWhere('account', 'Accumulated Depreciation')['amount'])->toBe('-10000.00')
        ->and(Decimal::compare($total('Total assets'), Decimal::add($total('Total liabilities'), $total('Total equity'))))->toBe(0);
});

it('classifies new asset and liability accounts as current by default and lets accountants change it', function () {
    Livewire::test(ChartScreen::class)->call('create')->set('code', '2700')->set('name', 'Bank loan (5 years)')->set('accountType', 'liability')
        ->set('term', 'non_current')->call('save')->assertHasNoErrors();
    expect(GlAccount::query()->where('code', '2700')->value('term'))->toBe('non_current')
        ->and(GlAccount::system('sales')->term)->toBeNull()
        ->and(GlAccount::system('fixed_assets')->term)->toBe('non_current');
});

it('builds a direct-method cash flow statement that agrees with the cash and bank balances', function () {
    foreach (['PKR', 'USD', 'EUR'] as $currency) {
        $rows = rowsOf('cash-flow', ['from' => '2026-09-01', 'to' => '2026-11-30', 'currency' => $currency]);
        $actual = (string) DB::table('journal_lines')->whereNotNull('money_account_id')->where('currency_code', $currency)->where('line_date', '<=', '2026-11-30')->sum(DB::raw('debit - credit'));

        expect($rows->firstWhere('line', 'Cash at the end')['amount'])->toBe(Decimal::round($actual));
    }

    $pkr = rowsOf('cash-flow', ['from' => '2026-09-01', 'to' => '2026-11-30', 'currency' => 'PKR'])->keyBy('line');
    $usd = rowsOf('cash-flow', ['from' => '2026-09-01', 'to' => '2026-11-30', 'currency' => 'USD'])->keyBy('line');

    expect($pkr['Capital introduced']['amount'])->toBe('8500000.00')
        ->and($pkr['Received from conversions']['amount'])->toBe('1395000.00')
        ->and($pkr->has('Transfers between cash and bank accounts'))->toBeFalse()   // bank → cash in one journal nets to zero
        ->and(Decimal::compare($pkr['Payments to vendors']['amount'], '0'))->toBe(-1)
        ->and($usd['Receipts from customers']['amount'])->toBe('9350.00')     // 7,850 net of bank charges + 1,500 advance
        ->and($usd['Converted to another currency']['amount'])->toBe('-5000.00');
});
