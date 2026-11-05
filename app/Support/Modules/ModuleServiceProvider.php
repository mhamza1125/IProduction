<?php

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use ReflectionClass;

/**
 * Base class for every business module (Core, Inventory, Sales, ...).
 *
 * A module lives in app/Modules/{Name} and follows this layout:
 *
 *   {Name}ServiceProvider.php      extends this class
 *   Models/, Services/, Livewire/  PHP code (namespace App\Modules\{Name}\...)
 *   Database/Migrations/           loaded automatically
 *   Database/Seeders/, Factories/  namespace App\Modules\{Name}\Database\...
 *   resources/views/               view namespace "{slug}::"
 *   resources/views/livewire/      views for the module's Livewire components
 *   routes/web.php                 loaded inside the "web" middleware group
 *
 * Register the provider in bootstrap/providers.php.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Human readable module name, e.g. "Core" or "Inventory".
     */
    abstract public function name(): string;

    /**
     * Sidebar entries contributed by this module.
     *
     * @return list<NavItem>
     */
    public function navigation(): array
    {
        return [];
    }

    /**
     * Permissions this module defines, as name => description.
     * Names follow "{module}.{resource}.{action}", e.g. "core.users.manage".
     * Run `php artisan permissions:sync` after changing them.
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        return [];
    }

    public function slug(): string
    {
        return Str::kebab($this->name());
    }

    public function register(): void
    {
        if (! $this->app->bound(ModuleRegistry::class)) {
            $this->app->singleton(ModuleRegistry::class);
        }

        $this->app->make(ModuleRegistry::class)->add($this);
    }

    public function boot(): void
    {
        $path = $this->modulePath();
        $slug = $this->slug();

        if (is_dir($path.'/Database/Migrations')) {
            $this->loadMigrationsFrom($path.'/Database/Migrations');
        }

        if (is_dir($path.'/resources/views')) {
            $this->loadViewsFrom($path.'/resources/views', $slug);
        }

        if (is_file($path.'/routes/web.php') && ! $this->app->routesAreCached()) {
            Route::middleware('web')->group($path.'/routes/web.php');
        }

        // Lets views use <livewire:{slug}::users.index /> for nested components.
        Livewire::addNamespace(
            $slug,
            classNamespace: $this->moduleNamespace().'\\Livewire',
            classPath: $path.'/Livewire',
            classViewPath: $path.'/resources/views/livewire',
        );
    }

    public function modulePath(): string
    {
        return dirname((string) (new ReflectionClass($this))->getFileName());
    }

    protected function moduleNamespace(): string
    {
        return (new ReflectionClass($this))->getNamespaceName();
    }
}
