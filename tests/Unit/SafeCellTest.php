<?php

use App\Support\Exports\SafeCell;

it('neutralises spreadsheet formulas in exported text but keeps numbers numeric', function () {
    expect(SafeCell::value('=HYPERLINK("http://x","click")'))->toBe('\'=HYPERLINK("http://x","click")')
        ->and(SafeCell::value('+92 300 1234567'))->toBe("'+92 300 1234567")
        ->and(SafeCell::value('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(SafeCell::value("\t=1"))->toBe("'\t=1")
        ->and(SafeCell::value('-cmd'))->toBe("'-cmd")
        ->and(SafeCell::value('-960.00'))->toBe('-960.00')
        ->and(SafeCell::value('Northfield'))->toBe('Northfield')
        ->and(SafeCell::value(12.5))->toBe(12.5)
        ->and(SafeCell::value(null))->toBeNull()
        ->and(SafeCell::rows([['=1', 'a'], ['b', '+2']]))->toBe([["'=1", 'a'], ['b', '+2']]);
});
