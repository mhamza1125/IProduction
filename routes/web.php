<?php

use App\Modules\Reports\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

// Module routes live in app/Modules/{Name}/routes/web.php.

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::view('profile', 'profile')->name('profile');
});
