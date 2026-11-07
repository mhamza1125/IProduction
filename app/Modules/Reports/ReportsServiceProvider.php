<?php

namespace App\Modules\Reports;

use App\Modules\Reports\Services\OrderCosting;
use App\Modules\Reports\Services\Reports;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Costing, reports and the dashboard (specification, Modules 12 and 14).
 * Reports read posted data only; cost, salary and profit figures need their
 * own permissions.
 */
class ReportsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Reports';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(Reports::class);
        $this->app->singleton(OrderCosting::class);
    }

    public function boot(): void
    {
        parent::boot();

        foreach (glob(__DIR__.'/Definitions/*/*.php') ?: [] as $file) {
            $class = __NAMESPACE__.'\\Definitions\\'.basename(dirname($file)).'\\'.basename($file, '.php');
            Reports::register($class);
        }
    }

    public function permissions(): array
    {
        return [new PermissionSet('reports', 'Reports', ['view', 'print', 'export'], 'Reports')];
    }

    public function navigation(): array
    {
        return [new NavItem('Reports', 'reports.index', 'chart', 'reports.view', 'Reports', 800, 'reports.*')];
    }
}
