<?php

use App\Modules\Core\Livewire\Shared\ActivityHistory;

it('shows logged values the way people read them', function (mixed $value, string $shown) {
    config(['app.timezone' => 'Asia/Karachi']);

    expect(ActivityHistory::display($value))->toBe($shown);
})->with([
    'a date logged in UTC' => ['2026-10-04T19:00:00.000000Z', '05-10-2026'],
    'a moment logged in UTC' => ['2026-11-10T07:22:00.000000Z', '10-11-2026 12:22'],
    'a plain date' => ['2026-10-05', '05-10-2026'],
    'nothing' => [null, '—'],
    'empty text' => ['', '—'],
    'true' => [true, 'Yes'],
    'false' => [false, 'No'],
    'an amount' => ['4856.00', '4856.00'],
    'a list' => [['a', 'b'], '["a","b"]'],
]);
