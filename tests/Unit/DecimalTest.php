<?php

use App\Support\Decimal;

it('adds without floating point errors', function () {
    expect(Decimal::add('0.1', '0.2'))->toBe('0.30')
        ->and(0.1 + 0.2)->not->toBe(0.3); // why this class exists
});

it('rounds half up at the requested scale', function () {
    expect(Decimal::round('2.345'))->toBe('2.35')
        ->and(Decimal::round('2.344'))->toBe('2.34')
        ->and(Decimal::round('-2.345'))->toBe('-2.35')
        ->and(Decimal::mul('3', '1.3333', Decimal::QUANTITY))->toBe('3.9999');
});

it('sums a list and treats null as zero', function () {
    expect(Decimal::sum(['100.50', '200.25', null, 3]))->toBe('303.75');
});

it('divides with rounding and rejects division by zero', function () {
    expect(Decimal::div('10', '3'))->toBe('3.33')
        ->and(Decimal::div('10', '3', Decimal::RATE))->toBe('3.333333');

    Decimal::div('1', '0');
})->throws(InvalidArgumentException::class, 'Division by zero.');

it('rejects non numeric strings', function () {
    Decimal::of('12abc');
})->throws(InvalidArgumentException::class);

it('compares values', function () {
    expect(Decimal::compare('1.10', '1.1'))->toBe(0)
        ->and(Decimal::compare('2', '10'))->toBe(-1)
        ->and(Decimal::isZero('0.000'))->toBeTrue()
        ->and(Decimal::isNegative('-0.01'))->toBeTrue();
});
