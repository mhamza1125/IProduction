<?php

namespace App\Modules\Core\Livewire\Roles;

use App\Modules\Core\Console\SyncPermissions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Title('Roles')]
class Index extends Component
{
    public function delete(int $id): void
    {
        $this->authorize('core.roles.manage');

        $role = Role::query()->withCount('users')->findOrFail($id);

        if ($role->name === SyncPermissions::SUPER_ADMIN) {
            $this->dispatch('toast', type: 'error', message: 'The Super Admin role cannot be deleted.');

            return;
        }

        if ($role->users_count > 0) {
            $this->dispatch('toast', type: 'error', message: "Remove the role from its {$role->users_count} user(s) first.");

            return;
        }

        $role->delete();

        $this->dispatch('toast', message: "Role {$role->name} deleted.");
    }

    public function render(): View
    {
        return view('core::livewire.roles.index', [
            'roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get(),
            'superAdmin' => SyncPermissions::SUPER_ADMIN,
        ]);
    }
}
