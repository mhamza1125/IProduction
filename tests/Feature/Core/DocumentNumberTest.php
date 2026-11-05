<?php

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Core\Services\FiscalYear;
use App\Modules\Core\Services\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

it('labels a July-June fiscal year', function () {
    $fy = app(FiscalYear::class);

    expect($fy->label(Carbon::parse('2026-07-01')))->toBe('2026-27')
        ->and($fy->label(Carbon::parse('2027-06-30')))->toBe('2026-27')
        ->and($fy->label(Carbon::parse('2026-06-30')))->toBe('2025-26')
        ->and($fy->start(Carbon::parse('2027-01-15'))->toDateString())->toBe('2026-07-01')
        ->and($fy->end(Carbon::parse('2027-01-15'))->toDateString())->toBe('2027-06-30');
});

it('labels a calendar fiscal year', function () {
    app(Settings::class)->set('fiscal_year.start_month', 1);

    expect(app(FiscalYear::class)->label(Carbon::parse('2026-11-05')))->toBe('2026');
});

it('issues sequential numbers per key and fiscal year', function () {
    $numbers = app(DocumentNumber::class);
    $date = Carbon::parse('2026-11-05');

    $issued = DB::transaction(fn () => [
        $numbers->next('sales_order', 'SO', $date),
        $numbers->next('sales_order', 'SO', $date),
        $numbers->next('purchase_order', 'PO', $date),
        $numbers->next('sales_order', 'SO', Carbon::parse('2027-07-01')),
        $numbers->next('item', 'ITM', perFiscalYear: false, padding: 4),
    ]);

    expect($issued)->toBe([
        'SO/2026-27/00001',
        'SO/2026-27/00002',
        'PO/2026-27/00001',
        'SO/2027-28/00001',
        'ITM/0001',
    ]);
});

it('does not use up a number when the transaction rolls back', function () {
    $numbers = app(DocumentNumber::class);
    $date = Carbon::parse('2026-11-05');

    try {
        DB::transaction(function () use ($numbers, $date) {
            $numbers->next('invoice', 'INV', $date);
            throw new RuntimeException('save failed');
        });
    } catch (RuntimeException) {
    }

    expect(DB::transaction(fn () => $numbers->next('invoice', 'INV', $date)))->toBe('INV/2026-27/00001');
});

it('refuses to run outside a transaction', function () {
    // RefreshDatabase wraps each test in a transaction, so leave it first.
    DB::rollBack();

    try {
        app(DocumentNumber::class)->next('invoice', 'INV');
    } finally {
        DB::beginTransaction();
    }
})->throws(LogicException::class);
