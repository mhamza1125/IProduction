<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::middleware('auth')->name('people.')->group(function () {
    foreach ([
        'customers' => 'App\\Modules\\People\\Livewire\\Customers',
        'vendors' => 'App\\Modules\\People\\Livewire\\Vendors',
        'employees' => 'App\\Modules\\People\\Livewire\\Employees',
        'contractors' => 'App\\Modules\\People\\Livewire\\Contractors',
    ] as $resource => $namespace) {
        $model = Str::singular($resource);

        Route::get($resource, $namespace.'\\Index')->middleware("can:{$resource}.view")->name("{$resource}.index");
        Route::get("{$resource}/create", $namespace.'\\Form')->middleware("can:{$resource}.create")->name("{$resource}.create");
        Route::get("{$resource}/{{$model}}", $namespace.'\\Show')->middleware("can:{$resource}.view")->name("{$resource}.show");
        Route::get("{$resource}/{{$model}}/edit", $namespace.'\\Form')->middleware("can:{$resource}.edit")->name("{$resource}.edit");
    }
});
