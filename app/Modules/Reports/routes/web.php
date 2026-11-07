<?php

use App\Modules\Reports\Http\ReportPrintController;
use App\Modules\Reports\Livewire;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('reports')->name('reports.')->group(function () {
    Route::get('/', Livewire\Index::class)->middleware('can:reports.view')->name('index');
    Route::get('order-cost/{order}', Livewire\OrderCost::class)->middleware('can:cost.view')->name('order-cost');
    Route::get('{key}', Livewire\Show::class)->middleware('can:reports.view')->name('show');
    Route::get('{key}/print', ReportPrintController::class)->middleware('can:reports.print')->name('print');
});
