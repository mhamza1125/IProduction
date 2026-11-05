<?php

use App\Modules\Core\Livewire\Currencies;
use App\Modules\Core\Livewire\Roles;
use App\Modules\Core\Livewire\Settings;
use App\Modules\Core\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('admin')->name('core.')->group(function () {
    Route::get('users', Users\Index::class)->middleware('can:core.users.view')->name('users.index');
    Route::get('users/create', Users\Form::class)->middleware('can:core.users.manage')->name('users.create');
    Route::get('users/{user}/edit', Users\Form::class)->middleware('can:core.users.manage')->name('users.edit');

    Route::get('roles', Roles\Index::class)->middleware('can:core.roles.manage')->name('roles.index');
    Route::get('roles/create', Roles\Form::class)->middleware('can:core.roles.manage')->name('roles.create');
    Route::get('roles/{role}/edit', Roles\Form::class)->middleware('can:core.roles.manage')->name('roles.edit');

    Route::get('currencies', Currencies\Index::class)->middleware('can:core.currencies.manage')->name('currencies.index');

    Route::get('settings/company', Settings\Company::class)->middleware('can:core.settings.manage')->name('settings.company');
});
