<?php

use App\Modules\Delivery\Http\PrintController;
use App\Modules\Delivery\Livewire\CreditNotes;
use App\Modules\Delivery\Livewire\Deliveries;
use App\Modules\Delivery\Livewire\Invoices;
use App\Modules\Delivery\Livewire\Returns;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('sales')->name('delivery.')->group(function () {
    Route::get('deliveries', Deliveries\Index::class)->middleware('can:deliveries.view')->name('deliveries.index');
    Route::get('deliveries/create', Deliveries\Form::class)->middleware('can:deliveries.create')->name('deliveries.create');
    Route::get('deliveries/{delivery}', Deliveries\Show::class)->middleware('can:deliveries.view')->name('deliveries.show');
    Route::get('deliveries/{delivery}/edit', Deliveries\Form::class)->middleware('can:deliveries.edit')->name('deliveries.edit');
    Route::get('deliveries/{delivery}/print', [PrintController::class, 'delivery'])->middleware('can:deliveries.print')->name('deliveries.print');
    Route::get('deliveries/{delivery}/packing-list', [PrintController::class, 'packing'])->middleware('can:packing_lists.print')->name('deliveries.packing');

    Route::get('invoices', Invoices\Index::class)->middleware('can:invoices.view')->name('invoices.index');
    Route::get('invoices/create', Invoices\Form::class)->middleware('can:invoices.create')->name('invoices.create');
    Route::get('invoices/{invoice}', Invoices\Show::class)->middleware('can:invoices.view')->name('invoices.show');
    Route::get('invoices/{invoice}/edit', Invoices\Form::class)->middleware('can:invoices.edit')->name('invoices.edit');
    Route::get('invoices/{invoice}/print', [PrintController::class, 'invoice'])->middleware('can:invoices.print')->name('invoices.print');

    Route::get('returns', Returns\Index::class)->middleware('can:delivery_returns.view')->name('returns.index');
    Route::get('returns/create', Returns\Form::class)->middleware('can:delivery_returns.create')->name('returns.create');
    Route::get('returns/{return}', Returns\Show::class)->middleware('can:delivery_returns.view')->name('returns.show');
    Route::get('returns/{return}/edit', Returns\Form::class)->middleware('can:delivery_returns.edit')->name('returns.edit');
    Route::get('returns/{return}/print', [PrintController::class, 'return'])->middleware('can:delivery_returns.print')->name('returns.print');

    Route::get('credit-notes', CreditNotes\Index::class)->middleware('can:credit_notes.view')->name('credit-notes.index');
    Route::get('credit-notes/create', CreditNotes\Form::class)->middleware('can:credit_notes.create')->name('credit-notes.create');
    Route::get('credit-notes/{note}', CreditNotes\Show::class)->middleware('can:credit_notes.view')->name('credit-notes.show');
    Route::get('credit-notes/{note}/edit', CreditNotes\Form::class)->middleware('can:credit_notes.edit')->name('credit-notes.edit');
    Route::get('credit-notes/{note}/print', [PrintController::class, 'creditNote'])->middleware('can:credit_notes.print')->name('credit-notes.print');
});
