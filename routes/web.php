<?php

use Illuminate\Support\Facades\Route;

// Module routes live in app/Modules/{Name}/routes/web.php.

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('profile', 'profile')->name('profile');
});
