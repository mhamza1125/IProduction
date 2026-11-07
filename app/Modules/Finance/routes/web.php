<?php

use App\Modules\Finance\Http\LedgerPrintController;
use App\Modules\Finance\Http\PaymentPrintController;
use App\Modules\Finance\Http\VoucherPrintController;
use App\Modules\Finance\Livewire\Accounts;
use App\Modules\Finance\Livewire\Assets;
use App\Modules\Finance\Livewire\ChartOfAccounts;
use App\Modules\Finance\Livewire\Conversions;
use App\Modules\Finance\Livewire\Expenses;
use App\Modules\Finance\Livewire\Journals;
use App\Modules\Finance\Livewire\Ledgers;
use App\Modules\Finance\Livewire\Payments;
use App\Modules\Finance\Livewire\Receipts;
use App\Modules\Finance\Livewire\Transfers;
use App\Modules\Finance\Livewire\Vouchers;
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

    Route::get('receipts', Receipts\Index::class)->middleware('can:receipts.view')->name('receipts.index');
    Route::get('receipts/create', Receipts\Form::class)->middleware('can:receipts.create')->name('receipts.create');
    Route::get('receipts/{receipt}', Receipts\Show::class)->middleware('can:receipts.view')->name('receipts.show');
    Route::get('receipts/{receipt}/edit', Receipts\Form::class)->middleware('can:receipts.edit')->name('receipts.edit');
    Route::get('conversions', Conversions\Index::class)->middleware('can:currency_conversions.view')->name('conversions.index');
    Route::get('conversions/create', Conversions\Form::class)->middleware('can:currency_conversions.create')->name('conversions.create');
    Route::get('conversions/{conversion}', Conversions\Show::class)->middleware('can:currency_conversions.view')->name('conversions.show');
    Route::get('conversions/{conversion}/edit', Conversions\Form::class)->middleware('can:currency_conversions.edit')->name('conversions.edit');
    Route::get('transfers', Transfers\Index::class)->middleware('can:transfers.view')->name('transfers.index');
    Route::get('transfers/create', Transfers\Form::class)->middleware('can:transfers.create')->name('transfers.create');
    Route::get('transfers/{transfer}', Transfers\Show::class)->middleware('can:transfers.view')->name('transfers.show');
    Route::get('transfers/{transfer}/edit', Transfers\Form::class)->middleware('can:transfers.edit')->name('transfers.edit');
    Route::get('expenses', Expenses\Index::class)->middleware('can:expenses.view')->name('expenses.index');
    Route::get('expenses/create', Expenses\Form::class)->middleware('can:expenses.create')->name('expenses.create');
    Route::get('expenses/{expense}', Expenses\Show::class)->middleware('can:expenses.view')->name('expenses.show');
    Route::get('expenses/{expense}/edit', Expenses\Form::class)->middleware('can:expenses.edit')->name('expenses.edit');
    Route::get('vouchers', Vouchers\Index::class)->middleware('can:journal_vouchers.view')->name('vouchers.index');
    Route::get('vouchers/create', Vouchers\Form::class)->middleware('can:journal_vouchers.create')->name('vouchers.create');
    Route::get('vouchers/{voucher}', Vouchers\Show::class)->middleware('can:journal_vouchers.view')->name('vouchers.show');
    Route::get('vouchers/{voucher}/edit', Vouchers\Form::class)->middleware('can:journal_vouchers.edit')->name('vouchers.edit');
    Route::get('receipts/{receipt}/print', [VoucherPrintController::class, 'receipt'])->middleware('can:receipts.print')->name('receipts.print');
    Route::get('vouchers/{voucher}/print', [VoucherPrintController::class, 'voucher'])->middleware('can:journal_vouchers.print')->name('vouchers.print');
    Route::get('assets', Assets\Index::class)->middleware('can:assets.view')->name('assets.index');
    Route::get('assets/create', Assets\Form::class)->middleware('can:assets.create')->name('assets.create');
    Route::get('assets/{asset}', Assets\Form::class)->middleware('can:assets.view')->name('assets.edit');
});
