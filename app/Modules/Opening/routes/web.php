<?php

use App\Modules\Opening\Http\TemplateController;
use App\Modules\Opening\Livewire;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('settings/opening')->name('opening.')->group(function () {
    Route::get('/', Livewire\Index::class)->middleware('can:opening.view')->name('index');
    Route::get('templates/{type}', TemplateController::class)->middleware('can:opening.view')->name('template');
    Route::get('{batch}', Livewire\Show::class)->middleware('can:opening.view')->name('show');
});
