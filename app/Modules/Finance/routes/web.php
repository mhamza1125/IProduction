<?php

use App\Modules\Finance\Livewire\ChartOfAccounts;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('finance')->name('finance.')->group(function () {
    Route::get('chart-of-accounts', ChartOfAccounts\Index::class)->middleware('can:chart_of_accounts.view')->name('chart-of-accounts');
});
