<?php

use App\Modules\Core\Services\Alerts;
use Illuminate\Support\Facades\Schedule;

// Requires the cron entry: * * * * * php /path/to/artisan schedule:run

// Failures raise an alert in the app's notification bell (the system sends no email).
Schedule::command('db:backup')
    ->dailyAt((string) config('iproduction.backup.time'))
    ->onOneServer()
    ->withoutOverlapping()
    ->onFailure(fn () => app(Alerts::class)->toAdmins('Backup failed', 'The nightly database backup did not finish. Run "php artisan db:backup" on the server to see why.', key: 'backup'));

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Last month's depreciation on the 1st, only when switched on in Settings › Company.
Schedule::command('assets:depreciate --scheduled')
    ->monthlyOn(1, '06:10')
    ->onOneServer()
    ->withoutOverlapping()
    ->onFailure(fn () => app(Alerts::class)->toAdmins('Depreciation failed', 'Last month\'s depreciation was not posted. Run "php artisan assets:depreciate" on the server to see why.', key: 'depreciation'));
