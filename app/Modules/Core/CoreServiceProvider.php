<?php

namespace App\Modules\Core;

use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Core\Services\Settings;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;

/**
 * Core: things every other module depends on.
 * Users, roles, company settings, currencies and document numbering.
 */
class CoreServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Core';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(Settings::class);
        $this->app->singleton(DocumentNumber::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissions::class]);
        }
    }

    public function permissions(): array
    {
        return [
            'core.users.view' => 'View users',
            'core.users.manage' => 'Create, edit and deactivate users',
            'core.roles.manage' => 'Manage roles and their permissions',
            'core.settings.manage' => 'Edit company settings',
            'core.currencies.manage' => 'Manage currencies',
            'core.system.horizon' => 'Open the queue dashboard (Horizon)',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Users', 'core.users.index', 'users', 'core.users.view', 'Administration', 900, 'core.users.*'),
            new NavItem('Roles', 'core.roles.index', 'shield', 'core.roles.manage', 'Administration', 910, 'core.roles.*'),
            new NavItem('Currencies', 'core.currencies.index', 'currency', 'core.currencies.manage', 'Administration', 920),
            new NavItem('Company settings', 'core.settings.company', 'cog', 'core.settings.manage', 'Administration', 930),
        ];
    }
}
