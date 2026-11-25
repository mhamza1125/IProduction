<?php

namespace App\Modules\Core;

use App\Modules\Core\Console\BackupDatabase;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Core\Console\VerifyBackup;
use App\Modules\Core\Services\Alerts;
use App\Modules\Core\Services\ApprovalInbox;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Core\Services\Settings;
use App\Support\Documents\Approvals;
use App\Support\Documents\DocumentCatalog;
use App\Support\Documents\DocumentWorkflow;
use App\Support\Documents\PeriodLock;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;

/**
 * Core (the "Settings" menu): users, roles, company settings, currencies,
 * numbering, approvals, period lock, audit log, plus the shared services
 * every module uses (settings, numbering, posting workflow).
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
        $this->app->singleton(Approvals::class);
        $this->app->singleton(PeriodLock::class);
        $this->app->singleton(DocumentWorkflow::class);
        $this->app->singleton(DocumentCatalog::class);
        $this->app->singleton(ApprovalInbox::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncPermissions::class, BackupDatabase::class, VerifyBackup::class]);
        }

        // A queued job that gives up (after its retries) raises an alert in the bell.
        Queue::failing(function (JobFailed $event) {
            $job = $event->job->resolveName();
            app(Alerts::class)->toAdmins('Background job failed', class_basename($job).': '.str($event->exception->getMessage())->limit(300),
                key: 'job-failed:'.$job);
        });
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('settings', 'Settings', ['view', 'edit'], 'Settings'),
            new PermissionSet('users', 'Users', ['view', 'create', 'edit', 'export'], 'Settings'),
            new PermissionSet('roles', 'Roles', ['view', 'create', 'edit', 'delete'], 'Settings'),
            new PermissionSet('currencies', 'Currencies', ['view', 'create', 'edit'], 'Settings'),
            new PermissionSet('units', 'Units', ['view', 'create', 'edit', 'delete'], 'Settings'),
            new PermissionSet('stages', 'Production stages', ['view', 'create', 'edit', 'delete'], 'Settings'),
            new PermissionSet('lookups', 'Lookups', ['view', 'create', 'edit', 'delete'], 'Settings'),
            new PermissionSet('audit_log', 'Audit log', ['view', 'export'], 'Settings'),
            new PermissionSet('period_lock', 'Period lock', ['edit', 'override'], 'Settings'),
            // Sensitive data: hidden everywhere unless the user has these.
            new PermissionSet('cost', 'Cost data', ['view'], 'Sensitive data'),
            new PermissionSet('profit', 'Profit data', ['view'], 'Sensitive data'),
            new PermissionSet('salary', 'Salaries', ['view'], 'Sensitive data'),
            new PermissionSet('bank_balance', 'Bank and cash balances', ['view'], 'Sensitive data'),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Company', 'core.settings.company', 'cog', 'settings.view', 'Settings', 900, 'core.settings.*'),
            new NavItem('Users', 'core.users.index', 'users', 'users.view', 'Settings', 910, 'core.users.*'),
            new NavItem('Roles', 'core.roles.index', 'shield', 'roles.view', 'Settings', 920, 'core.roles.*'),
            new NavItem('Units', 'core.units.index', 'scale', 'units.view', 'Settings', 930),
            new NavItem('Currencies', 'core.currencies.index', 'currency', 'currencies.view', 'Settings', 940),
            new NavItem('Stages', 'core.stages.index', 'arrows', 'stages.view', 'Settings', 950),
            new NavItem('Lookups', 'core.lookups.index', 'list', 'lookups.view', 'Settings', 960),
            new NavItem('Numbering', 'core.numbering', 'hashtag', 'settings.view', 'Settings', 970),
            new NavItem('Audit Log', 'core.audit-log', 'clock', 'audit_log.view', 'Settings', 990),
        ];
    }
}
