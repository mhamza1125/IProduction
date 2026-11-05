<?php

use App\Modules\Core\Console\SyncPermissions;
use App\Support\Format;

it('formats dates as dd-mm-yyyy', function () {
    expect(Format::date('2026-11-05'))->toBe('05-11-2026')
        ->and(Format::date(null))->toBe('');
});

it('formats money and quantities without floats', function () {
    expect(Format::money('1234567.5'))->toBe('1,234,567.50')
        ->and(Format::money('-2000', 'USD'))->toBe('USD -2,000.00')
        ->and(Format::money('0.005'))->toBe('0.01')
        ->and(Format::qty('12.5000'))->toBe('12.5')
        ->and(Format::qty('1000'))->toBe('1,000')
        ->and(Format::qty('0.0001'))->toBe('0.0001');
});

it('matches default role permission patterns', function () {
    $sales = ['allow' => ['sales_orders.*', '*.view'], 'except' => ['*.approve']];

    expect(SyncPermissions::matches('sales_orders.create', $sales))->toBeTrue()
        ->and(SyncPermissions::matches('sales_orders.approve', $sales))->toBeFalse()
        ->and(SyncPermissions::matches('vendors.view', $sales))->toBeTrue()
        ->and(SyncPermissions::matches('vendors.edit', $sales))->toBeFalse();
});
