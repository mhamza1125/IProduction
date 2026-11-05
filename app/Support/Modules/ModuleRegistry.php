<?php

namespace App\Support\Modules;

use App\Models\User;

/**
 * Collects every registered module so the app shell can build the sidebar
 * and the permission list without knowing about individual modules.
 */
class ModuleRegistry
{
    /** @var array<string, ModuleServiceProvider> */
    private array $modules = [];

    public function add(ModuleServiceProvider $module): void
    {
        $this->modules[$module->name()] = $module;
    }

    /**
     * @return array<string, ModuleServiceProvider>
     */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * All permissions declared by all modules, as name => description.
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->modules as $module) {
            $permissions += $module->permissions();
        }

        ksort($permissions);

        return $permissions;
    }

    /**
     * Sidebar groups visible to the given user.
     *
     * @return array<string, list<NavItem>>
     */
    public function navigationFor(?User $user): array
    {
        $items = [];

        foreach ($this->modules as $module) {
            foreach ($module->navigation() as $item) {
                if ($item->permission !== null && ($user === null || ! $user->can($item->permission))) {
                    continue;
                }

                $items[] = $item;
            }
        }

        usort($items, fn (NavItem $a, NavItem $b) => [$a->order, $a->label] <=> [$b->order, $b->label]);

        $groups = [];

        foreach ($items as $item) {
            $groups[$item->group][] = $item;
        }

        return $groups;
    }
}
