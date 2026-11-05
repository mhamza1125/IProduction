<?php

namespace App\Modules\Core\Livewire\Roles;

use App\Modules\Core\Console\SyncPermissions;
use App\Support\Modules\ModuleRegistry;
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
            abort_if($role->name === SyncPermissions::SUPER_ADMIN, 403, 'The Super Admin role always has every permission.');

            $this->role = $role;
            $this->name = $role->name;
            $this->permissions = $role->permissions->pluck('name')->values()->all();
        }
    }

    public function save(ModuleRegistry $modules): void
    {
        $this->authorize('core.roles.manage');

        $data = $this->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::notIn([SyncPermissions::SUPER_ADMIN]),
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
        // Group permissions by module for the checkbox matrix.
        $groups = [];

        foreach ($modules->all() as $module) {
            if ($module->permissions() !== []) {
                $groups[$module->name()] = $module->permissions();
            }
        }

        return view('core::livewire.roles.form', ['groups' => $groups])
            ->title($this->role ? 'Edit role' : 'New role');
    }
}
