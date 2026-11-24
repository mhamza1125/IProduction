<?php

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Core\Models\Lookup;
use App\Modules\Purchasing\Models\VendorBill;
use App\Modules\Reports\Services\Reports;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;

/** Tax summary, customer profitability, vendor bill printout and department filter, on the demo company. */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoDataSeeder::class);
    $this->actingAs(User::role(SyncPermissions::ADMIN)->firstOrFail());
    $this->reports = app(Reports::class);
});

function reportRows(string $key, array $filters): array
{
    return app(Reports::class)->find($key)->rows($filters)->map(fn ($r) => (array) $r)->all();
}

it('summarises output tax, untaxed sales, credit notes and input tax for a period', function () {
    $rows = collect(reportRows('tax-summary', ['from' => '2026-09-01', 'to' => '2026-11-30']));

    $untaxedUsd = $rows->first(fn ($r) => str_starts_with($r['section'], 'Sales without tax') && $r['currency'] === 'USD');
    $credit = $rows->first(fn ($r) => str_starts_with($r['section'], 'Credit notes'));
    $input = $rows->first(fn ($r) => $r['section'] === 'Input tax on vendor bills');

    expect($untaxedUsd['taxable'])->toBe('7900.00')
        ->and($credit)->currency->toBe('EUR')->taxable->toBe('-174.00')
        ->and($input)->tax->toBe('21240.00')->currency->toBe('PKR')
        ->and(reportRows('tax-summary', ['from' => '2025-01-01', 'to' => '2025-12-31']))->toBe([]);
});

it('shows margin per customer, valuing foreign revenue at the realized conversion rate', function () {
    $rows = collect(reportRows('customer-profitability', ['from' => '2026-01-01', 'to' => '2026-11-30']))->keyBy('customer');

    // USD 5,000 converted for PKR 1,395,000: 279 PKR per USD.
    expect($rows['Northfield Sports Inc'])->rate->toBe('279.0000')->revenue->toBe('7900.00')->revenue_pkr->toBe('2204100.00')
        ->and($rows['Northfield Sports Inc']['margin'])->not->toBeNull()
        // EUR was never converted: no rate, no guessed margin.
        ->and($rows['Rheinland Sportartikel GmbH'])->rate->toBeNull()->margin->toBeNull();

    $this->actingAs(userWith(['reports.view', 'cost.view']));
    $this->get(route('reports.show', 'customer-profitability'))->assertForbidden();
});

it('prints vendor bills once approved', function () {
    $bill = VendorBill::query()->whereNotNull('posted_at')->firstOrFail();

    $this->get(route('purchasing.bills.print', $bill))->assertOk()->assertSee('Purchase Invoice')->assertSee($bill->vendor_invoice_number)->assertSee('Goods receipt');
    $this->actingAs(userWith(['vendor_bills.view']));
    $this->get(route('purchasing.bills.print', $bill))->assertForbidden();
});

it('filters employee output by department', function () {
    $production = Lookup::query()->where('type', 'department')->where('name', 'Production')->value('id');
    $stores = Lookup::query()->where('type', 'department')->where('name', 'Stores')->value('id');

    expect(reportRows('employee-output', ['from' => '2026-09-01', 'to' => '2026-11-30', 'department' => $production]))->not->toBe([])
        ->and(reportRows('employee-output', ['from' => '2026-09-01', 'to' => '2026-11-30', 'department' => $stores]))->toBe([]);
});
