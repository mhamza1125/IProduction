<?php

use App\Modules\Sales\Http\PrintController;
use App\Modules\Sales\Livewire\Orders;
use App\Modules\Sales\Livewire\Planning;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('sales')->name('sales.')->group(function () {
    Route::get('orders', Orders\Index::class)->middleware('can:sales_orders.view')->name('orders.index');
    Route::get('orders/create', Orders\Form::class)->middleware('can:sales_orders.create')->name('orders.create');
    Route::get('orders/{order}', Orders\Show::class)->middleware('can:sales_orders.view')->name('orders.show');
    Route::get('orders/{order}/edit', Orders\Form::class)->middleware('can:sales_orders.edit')->name('orders.edit');
    Route::get('orders/{order}/print', [PrintController::class, 'order'])->middleware('can:sales_orders.print')->name('orders.print');
    Route::get('planning', Planning\Index::class)->middleware('can:planning.view')->name('planning');
});
