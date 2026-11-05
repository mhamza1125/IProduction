<?php

use Illuminate\Support\Facades\Schedule;

// Requires the cron entry: * * * * * php /path/to/artisan schedule:run

Schedule::command('db:backup')
    ->dailyAt((string) config('iproduction.backup.time'))
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();
