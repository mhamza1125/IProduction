<?php

namespace App\Modules\Core\Console;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    public const SUPER_ADMIN = 'Super Admin';

    protected $signature = 'permissions:sync {--prune : Delete permissions no module declares any more}';

    protected $description = 'Create the permissions declared by all modules, and the Super Admin role';

    public function handle(ModuleRegistry $modules, PermissionRegistrar $registrar): int
    {
        $declared = $modules->permissions();

        foreach (array_keys($declared) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(self::SUPER_ADMIN, 'web');

        if ($this->option('prune')) {
            $removed = Permission::query()->whereNotIn('name', array_keys($declared))->get();

            foreach ($removed as $permission) {
                $permission->delete();
                $this->line("Removed <comment>{$permission->name}</comment>");
            }
        }

        $registrar->forgetCachedPermissions();

        $this->info(count($declared).' permissions in sync.');

        return self::SUCCESS;
    }
}
