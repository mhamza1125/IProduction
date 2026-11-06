<?php

use App\Modules\Purchasing\Http\PrintController;
use App\Modules\Purchasing\Livewire\Bills;
use App\Modules\Purchasing\Livewire\Orders;
use App\Modules\Purchasing\Livewire\Receipts;
use App\Modules\Purchasing\Livewire\Returns;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('purchasing')->name('purchasing.')->group(function () {
    Route::get('orders', Orders\Index::class)->middleware('can:purchase_orders.view')->name('orders.index');
    Route::get('orders/create', Orders\Form::class)->middleware('can:purchase_orders.create')->name('orders.create');
    Route::get('orders/{order}', Orders\Show::class)->middleware('can:purchase_orders.view')->name('orders.show');
    Route::get('orders/{order}/edit', Orders\Form::class)->middleware('can:purchase_orders.edit')->name('orders.edit');
    Route::get('orders/{order}/print', [PrintController::class, 'order'])->middleware('can:purchase_orders.print')->name('orders.print');

    Route::get('receipts', Receipts\Index::class)->middleware('can:goods_receipts.view')->name('receipts.index');
    Route::get('receipts/create', Receipts\Form::class)->middleware('can:goods_receipts.create')->name('receipts.create');
    Route::get('receipts/{receipt}', Receipts\Show::class)->middleware('can:goods_receipts.view')->name('receipts.show');
    Route::get('receipts/{receipt}/edit', Receipts\Form::class)->middleware('can:goods_receipts.edit')->name('receipts.edit');
    Route::get('receipts/{receipt}/print', [PrintController::class, 'receipt'])->middleware('can:goods_receipts.print')->name('receipts.print');

    Route::get('bills', Bills\Index::class)->middleware('can:vendor_bills.view')->name('bills.index');
    Route::get('bills/create', Bills\Form::class)->middleware('can:vendor_bills.create')->name('bills.create');
    Route::get('bills/{bill}', Bills\Show::class)->middleware('can:vendor_bills.view')->name('bills.show');
    Route::get('bills/{bill}/edit', Bills\Form::class)->middleware('can:vendor_bills.edit')->name('bills.edit');

    Route::get('returns', Returns\Index::class)->middleware('can:purchase_returns.view')->name('returns.index');
    Route::get('returns/create', Returns\Form::class)->middleware('can:purchase_returns.create')->name('returns.create');
    Route::get('returns/{return}', Returns\Show::class)->middleware('can:purchase_returns.view')->name('returns.show');
    Route::get('returns/{return}/print', [PrintController::class, 'return'])->middleware('can:purchase_returns.print')->name('returns.print');
});
