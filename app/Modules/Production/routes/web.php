<?php

use App\Modules\Production\Http\PrintController;
use App\Modules\Production\Livewire\Jobs;
use App\Modules\Production\Livewire\Processes;
use App\Modules\Production\Livewire\Ptcs;
use App\Modules\Production\Livewire\Rejections;
use App\Modules\Production\Livewire\WageWork;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('production')->name('production.')->group(function () {
    Route::get('ptcs', Ptcs\Index::class)->middleware('can:ptcs.view')->name('ptcs.index');
    Route::get('ptcs/create', Ptcs\Form::class)->middleware('can:ptcs.create')->name('ptcs.create');
    Route::get('ptcs/{ptc}', Ptcs\Show::class)->middleware('can:ptcs.view')->name('ptcs.show');
    Route::get('ptcs/{ptc}/edit', Ptcs\Form::class)->middleware('can:ptcs.edit')->name('ptcs.edit');
    Route::get('ptcs/{ptc}/print', [PrintController::class, 'ptc'])->middleware('can:ptcs.print')->name('ptcs.print');

    Route::get('jobs', Jobs\Index::class)->middleware('can:contractor_jobs.view')->name('jobs.index');
    Route::get('jobs/create', Jobs\Form::class)->middleware('can:contractor_jobs.create')->name('jobs.create');
    Route::get('jobs/{job}', Jobs\Show::class)->middleware('can:contractor_jobs.view')->name('jobs.show');
    Route::get('jobs/{job}/edit', Jobs\Form::class)->middleware('can:contractor_jobs.edit')->name('jobs.edit');
    Route::get('jobs/{job}/print', [PrintController::class, 'job'])->middleware('can:contractor_jobs.print')->name('jobs.print');

    Route::get('processes', Processes\Index::class)->middleware('can:material_processes.view')->name('processes.index');
    Route::get('processes/create', Processes\Form::class)->middleware('can:material_processes.create')->name('processes.create');
    Route::get('processes/{process}', Processes\Show::class)->middleware('can:material_processes.view')->name('processes.show');
    Route::get('processes/{process}/edit', Processes\Form::class)->middleware('can:material_processes.edit')->name('processes.edit');

    Route::get('rejections', Rejections\Index::class)->middleware('can:rejections.view')->name('rejections.index');
    Route::get('rejections/{rejection}', Rejections\Show::class)->middleware('can:rejections.view')->name('rejections.show');

    Route::get('wage-work', WageWork\Index::class)->middleware('can:wage_work.view')->name('wage-work');
});
