<?php

use App\Modules\Payroll\Http\PrintController;
use App\Modules\Payroll\Livewire\Advances;
use App\Modules\Payroll\Livewire\AttendanceGrid;
use App\Modules\Payroll\Livewire\Loans;
use App\Modules\Payroll\Livewire\Runs;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('hr')->name('payroll.')->group(function () {
    Route::get('attendance', AttendanceGrid::class)->middleware('can:attendance.view')->name('attendance');

    Route::get('payroll', Runs\Index::class)->middleware('can:payroll.view')->name('runs.index');
    Route::get('payroll/{run}', Runs\Show::class)->middleware('can:payroll.view')->name('runs.show');
    Route::get('payroll/{run}/print', [PrintController::class, 'sheet'])->middleware('can:payroll.print')->name('runs.print');
    Route::get('payroll/{run}/slip/{line}', [PrintController::class, 'slip'])->middleware('can:payroll.print')->name('runs.slip');

    Route::get('loans', Loans\Index::class)->middleware('can:loans.view')->name('loans.index');
    Route::get('loans/create', Loans\Form::class)->middleware('can:loans.create')->name('loans.create');
    Route::get('loans/{loan}', Loans\Show::class)->middleware('can:loans.view')->name('loans.show');
    Route::get('loans/{loan}/edit', Loans\Form::class)->middleware('can:loans.edit')->name('loans.edit');

    Route::get('advances', Advances\Index::class)->middleware('can:advances.view')->name('advances.index');
    Route::get('advances/create', Advances\Form::class)->middleware('can:advances.create')->name('advances.create');
    Route::get('advances/{advance}', Advances\Show::class)->middleware('can:advances.view')->name('advances.show');
    Route::get('advances/{advance}/edit', Advances\Form::class)->middleware('can:advances.edit')->name('advances.edit');
});
