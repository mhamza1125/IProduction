<?php

namespace App\Support\Modules;

use App\Models\User;

/**
 * Collects every registered module so the app shell can build the sidebar,
 * the permission list and the document-type list without knowing about
 * individual modules.
 */
class ModuleRegistry
{
    /** Sidebar group order, as in the specification's application menu. */
    public const GROUP_ORDER = ['Sales', 'Purchasing', 'Production', 'Inventory', 'Catalog', 'Finance', 'HR', 'Assets', 'Reports', 'Settings'];

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
     * @return list<PermissionSet>
     */
    public function permissionSets(): array
    {
        $sets = [];

        foreach ($this->modules as $module) {
            foreach ($module->permissions() as $set) {
                $sets[] = $set;
            }
        }

        return $sets;
    }

    /**
     * All permissions declared by all modules, as name => description.
     *
     * @return array<string, string>
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->permissionSets() as $set) {
            $permissions += $set->permissions();
        }

        ksort($permissions);

        return $permissions;
    }

    /**
     * Document types declared by all modules.
     *
     * @return array<string, DocumentType>
     */
    public function documentTypes(): array
    {
        $types = [];

        foreach ($this->modules as $module) {
            foreach ($module->documentTypes() as $type) {
                $types[$type->key] = $type;
            }
        }

        return $types;
    }

    public function documentType(string $key): ?DocumentType
    {
        return $this->documentTypes()[$key] ?? null;
    }

    /**
     * Sidebar groups visible to the given user, in menu order.
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

        uksort($groups, function (string $a, string $b) {
            $ia = array_search($a, self::GROUP_ORDER, true);
            $ib = array_search($b, self::GROUP_ORDER, true);

            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
        });

        return $groups;
    }
}
