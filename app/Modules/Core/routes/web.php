<?php

use App\Modules\Core\Http\AttachmentController;
use App\Modules\Core\Livewire\AuditLog;
use App\Modules\Core\Livewire\Currencies;
use App\Modules\Core\Livewire\Roles;
use App\Modules\Core\Livewire\Settings;
use App\Modules\Core\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->name('core.')->group(function () {
    Route::get('attachments/{attachment}', AttachmentController::class)->name('attachments.download');

    Route::prefix('settings')->group(function () {
        Route::get('users', Users\Index::class)->middleware('can:users.view')->name('users.index');
        Route::get('users/create', Users\Form::class)->middleware('can:users.create')->name('users.create');
        Route::get('users/{user}/edit', Users\Form::class)->middleware('can:users.edit')->name('users.edit');

        Route::get('roles', Roles\Index::class)->middleware('can:roles.view')->name('roles.index');
        Route::get('roles/create', Roles\Form::class)->middleware('can:roles.create')->name('roles.create');
        Route::get('roles/{role}/edit', Roles\Form::class)->middleware('can:roles.edit')->name('roles.edit');

        Route::get('currencies', Currencies\Index::class)->middleware('can:currencies.view')->name('currencies.index');

        Route::get('company', Settings\Company::class)->middleware('can:settings.view')->name('settings.company');
        Route::get('approvals', Settings\Approvals::class)->middleware('can:settings.view')->name('settings.approvals');
        Route::get('period-lock', Settings\PeriodLock::class)->middleware('can:settings.view')->name('settings.period-lock');
        Route::get('numbering', Settings\Numbering::class)->middleware('can:settings.view')->name('numbering');

        Route::get('audit-log', AuditLog::class)->middleware('can:audit_log.view')->name('audit-log');
    });
});
