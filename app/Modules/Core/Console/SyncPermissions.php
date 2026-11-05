<?php

namespace App\Modules\Core\Console;

use App\Support\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every permission the modules declare, the default roles from
 * config/roles.php, and grants each newly created permission to the roles
 * whose default patterns match it. Safe to run on every deploy.
 */
class SyncPermissions extends Command
{
    /** The role that always has every permission. */
    public const ADMIN = 'Admin';

    protected $signature = 'permissions:sync {--prune : Delete permissions no module declares any more}';

    protected $description = 'Create module permissions and the default roles';

    public function handle(ModuleRegistry $modules, PermissionRegistrar $registrar): int
    {
        $declared = array_keys($modules->permissions());
        $existing = Permission::query()->pluck('name')->all();
        $new = array_values(array_diff($declared, $existing));

        foreach ($new as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(self::ADMIN, 'web');

        /** @var array<string, array{allow: list<string>, except?: list<string>}> $defaults */
        $defaults = config('roles.defaults', []);

        foreach ($defaults as $roleName => $rules) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            $grant = $role === null ? $declared : $new; // a new role gets all its defaults
            $role ??= Role::create(['name' => $roleName, 'guard_name' => 'web']);

            $matching = array_values(array_filter($grant, fn (string $permission) => self::matches($permission, $rules)));

            if ($matching !== []) {
                $role->givePermissionTo($matching);
            }
        }

        if ($this->option('prune')) {
            Permission::query()->whereNotIn('name', $declared)->get()->each(function (Permission $permission) {
                $permission->delete();
                $this->line("Removed <comment>{$permission->name}</comment>");
            });
        }

        $registrar->forgetCachedPermissions();

        $this->info(count($declared).' permissions in sync ('.count($new).' new).');

        return self::SUCCESS;
    }

    /**
     * @param  array{allow: list<string>, except?: list<string>}  $rules
     */
    public static function matches(string $permission, array $rules): bool
    {
        return Str::is($rules['allow'], $permission) && ! Str::is($rules['except'] ?? [], $permission);
    }
}
