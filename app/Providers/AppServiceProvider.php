<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
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

        // migrate:fresh, db:wipe and similar refuse to run in production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Admin passes every permission check.
        Gate::before(fn ($user) => $user instanceof User && $user->hasRole(SyncPermissions::ADMIN) ? true : null);

        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        $this->registerBladeDirectives();
        $this->registerBlueprintMacros();

        // Test-only tables (e.g. a sample document for the posting framework tests).
        if ($this->app->environment('testing')) {
            $this->loadMigrationsFrom(base_path('tests/Fixtures/migrations'));
            $this->app['view']->addLocation(base_path('tests/Fixtures'));
        }
    }

    private function registerBladeDirectives(): void
    {
        Blade::directive('date', fn (string $e) => "<?php echo e(\\App\\Support\\Format::date({$e})); ?>");
        Blade::directive('datetime', fn (string $e) => "<?php echo e(\\App\\Support\\Format::datetime({$e})); ?>");
        Blade::directive('money', fn (string $e) => "<?php echo e(\\App\\Support\\Format::money({$e})); ?>");
        Blade::directive('qty', fn (string $e) => "<?php echo e(\\App\\Support\\Format::qty({$e})); ?>");
    }

    private function registerBlueprintMacros(): void
    {
        /**
         * Columns used by HasDocumentWorkflow: $table->documentWorkflow();
         */
        Blueprint::macro('documentWorkflow', function () {
            /** @var Blueprint $this */
            $this->string('number', 30)->unique();
            $this->string('status', 30)->default('draft')->index();
            $this->foreignId('approved_by')->nullable()->constrained('users');
            $this->timestamp('approved_at')->nullable();
            $this->foreignId('posted_by')->nullable()->constrained('users');
            $this->timestamp('posted_at')->nullable();
            $this->foreignId('cancelled_by')->nullable()->constrained('users');
            $this->timestamp('cancelled_at')->nullable();
            $this->string('cancel_reason', 500)->nullable();
            $this->foreignId('created_by')->nullable()->constrained('users');
        });
    }
}
