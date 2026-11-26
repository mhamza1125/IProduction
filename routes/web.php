<?php

use App\Http\Controllers\DemoLoginController;
use App\Modules\Reports\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

// Module routes live in app/Modules/{Name}/routes/web.php.

Route::redirect('/', '/dashboard');

// Demo only: returns 404 unless DEMO_LOGIN=true (config/iproduction.php).
Route::post('demo-login', DemoLoginController::class)
    ->middleware(['guest', 'throttle:10,1'])
    ->name('demo-login');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::view('profile', 'profile')->name('profile');
});
