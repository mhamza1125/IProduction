<?php

/*
| Deployment-level settings for IProduction. Anything a company admin should
| be able to change from the UI belongs in the `settings` table instead
| (see App\Modules\Core\Services\Settings).
*/

return [

    // First Super Admin account, created by `php artisan db:seed`.
    // If ADMIN_PASSWORD is empty a random one is generated and printed once.
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
