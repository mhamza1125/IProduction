<?php

use App\Modules\Catalog\Livewire\Attributes;
use App\Modules\Catalog\Livewire\Boms;
use App\Modules\Catalog\Livewire\Items;
use App\Modules\Catalog\Livewire\Routes;
use App\Modules\Catalog\Livewire\Variants;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('catalog')->name('catalog.')->group(function () {
    Route::get('items', Items\Index::class)->middleware('can:items.view')->name('items.index');
    Route::get('items/create', Items\Form::class)->middleware('can:items.create')->name('items.create');
    Route::get('items/{item}', Items\Show::class)->middleware('can:items.view')->name('items.show');
    Route::get('items/{item}/edit', Items\Form::class)->middleware('can:items.edit')->name('items.edit');

    Route::get('variants', Variants\Index::class)->middleware('can:items.view')->name('variants.index');
    Route::get('attributes', Attributes\Index::class)->middleware('can:attributes.view')->name('attributes.index');

    Route::get('routes', Routes\Index::class)->middleware('can:routes.view')->name('routes.index');
    Route::get('routes/create', Routes\Form::class)->middleware('can:routes.create')->name('routes.create');
    Route::get('routes/{productionRoute}', Routes\Form::class)->middleware('can:routes.view')->name('routes.edit');

    Route::get('boms', Boms\Index::class)->middleware('can:boms.view')->name('boms.index');
    Route::get('boms/{bom}', Boms\Edit::class)->middleware('can:boms.view')->name('boms.edit');
});
