<?php

use App\Modules\Inventory\Livewire\Adjustments;
use App\Modules\Inventory\Livewire\Locations;
use App\Modules\Inventory\Livewire\Lots;
use App\Modules\Inventory\Livewire\Movements;
use App\Modules\Inventory\Livewire\Reservations;
use App\Modules\Inventory\Livewire\Stock;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('stock', Stock\Index::class)->middleware('can:stock.view')->name('stock');
    Route::get('movements', Movements\Index::class)->middleware('can:stock_movements.view')->name('movements');
    Route::get('lots', Lots\Index::class)->middleware('can:lots.view')->name('lots.index');
    Route::get('lots/{lot}', Lots\Show::class)->middleware('can:lots.view')->name('lots.show');
    Route::get('reservations', Reservations\Index::class)->middleware('can:reservations.view')->name('reservations');
    Route::get('locations', Locations\Index::class)->middleware('can:locations.view')->name('locations');

    Route::get('adjustments', Adjustments\Index::class)->middleware('can:stock_adjustments.view')->name('adjustments.index');
    Route::get('adjustments/create', Adjustments\Form::class)->middleware('can:stock_adjustments.create')->name('adjustments.create');
    Route::get('adjustments/count', Adjustments\Count::class)->middleware('can:stock_adjustments.create')->name('adjustments.count');
    Route::get('adjustments/{adjustment}', Adjustments\Show::class)->middleware('can:stock_adjustments.view')->name('adjustments.show');
    Route::get('adjustments/{adjustment}/edit', Adjustments\Form::class)->middleware('can:stock_adjustments.edit')->name('adjustments.edit');
});
