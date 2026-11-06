<?php

use App\Modules\Finance\Http\LedgerPrintController;
use App\Modules\Finance\Http\PaymentPrintController;
use App\Modules\Finance\Livewire\Accounts;
use App\Modules\Finance\Livewire\ChartOfAccounts;
use App\Modules\Finance\Livewire\Journals;
use App\Modules\Finance\Livewire\Ledgers;
use App\Modules\Finance\Livewire\Payments;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('finance')->name('finance.')->group(function () {
    Route::get('chart-of-accounts', ChartOfAccounts\Index::class)->middleware('can:chart_of_accounts.view')->name('chart-of-accounts');
    Route::get('accounts', Accounts\Index::class)->middleware('can:accounts.view')->name('accounts.index');
    Route::get('ledgers', Ledgers\Index::class)->middleware('can:ledgers.view')->name('ledgers');
    Route::get('ledgers/print', LedgerPrintController::class)->middleware('can:ledgers.print')->name('ledgers.print');
    Route::get('payments', Payments\Index::class)->middleware('can:payments.view')->name('payments.index');
    Route::get('payments/create', Payments\Form::class)->middleware('can:payments.create')->name('payments.create');
    Route::get('payments/{payment}', Payments\Show::class)->middleware('can:payments.view')->name('payments.show');
    Route::get('payments/{payment}/edit', Payments\Form::class)->middleware('can:payments.edit')->name('payments.edit');
    Route::get('payments/{payment}/print', PaymentPrintController::class)->middleware('can:payments.print')->name('payments.print');
    Route::get('journals', Journals\Index::class)->middleware('can:journals.view')->name('journals.index');
    Route::get('journals/{journal}', Journals\Show::class)->middleware('can:journals.view')->name('journals.show');
});
