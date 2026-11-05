<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class);
    }

    public function boot(): void
    {
        // Catch N+1 queries, silently discarded attributes and typos in
        // attribute names while developing. Production logs, never throws.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super Admins pass every permission check.
        Gate::before(fn ($user) => $user->hasRole(SyncPermissions::SUPER_ADMIN) ? true : null);

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
