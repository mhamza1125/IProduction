<?php

namespace App\Modules\Core\Livewire\Roles;

use App\Modules\Core\Console\SyncPermissions;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\PermissionSet;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Form extends Component
{
    #[Locked]
    public ?Role $role = null;

    public string $name = '';

    /** @var list<string> */
    public array $permissions = [];

    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            abort_if($role->name === SyncPermissions::ADMIN, 403, 'The Admin role always has every permission.');

            $this->role = $role;
            $this->name = $role->name;
            $this->permissions = $role->permissions->pluck('name')->values()->all();
        }
    }

    public function save(ModuleRegistry $modules): void
    {
        $this->authorize($this->role ? 'roles.edit' : 'roles.create');

        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::notIn([SyncPermissions::ADMIN]),
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->role?->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(array_keys($modules->permissions()))],
        ]);

        $role = $this->role ?? new Role(['guard_name' => 'web']);
        $role->name = $data['name'];
        $role->save();
        $role->syncPermissions($data['permissions']);

        session()->flash('toast', $this->role ? 'Role updated.' : 'Role created.');

        $this->redirectRoute('core.roles.index', navigate: true);
    }

    public function render(ModuleRegistry $modules): View
    {
        // Matrix: one row per resource, one column per action, grouped by menu section.
        $groups = [];

        foreach ($modules->permissionSets() as $set) {
            $groups[$set->group][] = $set;
        }

        return view('core::livewire.roles.form', [
            'groups' => $groups,
            'actions' => PermissionSet::ACTIONS,
        ])->title($this->role ? 'Edit role' : 'New role');
    }
}
