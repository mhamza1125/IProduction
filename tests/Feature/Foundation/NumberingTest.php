<?php

use App\Modules\Core\Livewire\Settings\Numbering;
use App\Modules\Core\Services\DocumentNumber;
use App\Support\Modules\ModuleRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('uses the specification format PREFIX-YY-0001 and restarts each year', function () {
    $numbers = app(DocumentNumber::class);

    $issued = DB::transaction(fn () => [
        $numbers->next('test_document', Carbon::parse('2026-11-05')),
        $numbers->next('test_document', Carbon::parse('2026-12-31')),
        $numbers->next('test_document', Carbon::parse('2027-01-01')),
    ]);

    expect($issued)->toBe(['TD-26-0001', 'TD-26-0002', 'TD-27-0001']);
});

it('does not use up a number when the transaction rolls back', function () {
    $numbers = app(DocumentNumber::class);

    try {
        DB::transaction(function () use ($numbers) {
            $numbers->next('test_document', Carbon::parse('2026-11-05'));
            throw new RuntimeException('save failed');
        });
    } catch (RuntimeException) {
    }

    expect(DB::transaction(fn () => $numbers->next('test_document', Carbon::parse('2026-11-05'))))->toBe('TD-26-0001');
});

it('refuses to run outside a transaction', function () {
    DB::rollBack(); // leave RefreshDatabase's transaction

    try {
        app(DocumentNumber::class)->next('test_document');
    } finally {
        DB::beginTransaction();
    }
})->throws(LogicException::class);

it('rejects unknown document types', function () {
    DB::transaction(fn () => app(DocumentNumber::class)->next('nonsense'));
})->throws(InvalidArgumentException::class);

it('lets an admin change the format and next number', function () {
    $this->actingAs(admin());

    Livewire::test(Numbering::class)
        ->call('edit', 'test_document')
        ->set('prefix', 'TEST')
        ->set('separator', '/')
        ->set('year_format', 'YYYY')
        ->set('padding', 5)
        ->set('next_number', 120)
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::transaction(fn () => app(DocumentNumber::class)->next('test_document', Carbon::parse('2025-03-01'))))
        ->toBe('TEST/2025/00001'); // next_number applies to the current year only

    expect(DB::transaction(fn () => app(DocumentNumber::class)->next('test_document')))
        ->toBe('TEST/'.now()->year.'/00120');
});

it('stops users without settings.edit from changing numbering', function () {
    $this->actingAs(userWith(['settings.view']));

    Livewire::test(Numbering::class)
        ->call('edit', 'test_document')
        ->set('prefix', 'HACK')
        ->call('save')
        ->assertForbidden();
});

it('gives every document type its own default prefix, so a number names one kind of document', function () {
    $prefixes = collect(app(ModuleRegistry::class)->documentTypes())->map(fn ($t) => $t->prefix);

    expect($prefixes->duplicates()->all())->toBe([]);
});
