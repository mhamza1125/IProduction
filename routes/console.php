<?php

use Illuminate\Support\Facades\Schedule;

// Requires the cron entry: * * * * * php /path/to/artisan schedule:run

Schedule::command('db:backup')
    ->dailyAt((string) config('iproduction.backup.time'))
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Last month's depreciation on the 1st, only when switched on in Settings › Company.
Schedule::command('assets:depreciate --scheduled')
    ->monthlyOn(1, '06:10')
    ->onOneServer()
    ->withoutOverlapping();
