<?php

/*
| Deployment-level settings for IProduction. Anything a company admin should
| be able to change from the UI belongs in the `settings` table instead
| (see App\Modules\Core\Services\Settings).
*/

return [

    // First Admin account, created by `php artisan db:seed`.
    // If ADMIN_PASSWORD is empty a random one is generated and printed once.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    // `php artisan db:backup` runs daily (routes/console.php).
    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/private/backups')),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
        'time' => env('BACKUP_TIME', '02:15'),
    ],

];
