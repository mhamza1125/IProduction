<?php

namespace App\Modules\Core\Livewire\Users;

use App\Models\User;
use App\Support\Livewire\WithDataTable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Users')]
class Index extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $status = '';

    protected function sortable(): array
    {
        return ['name', 'email', 'last_login_at', 'created_at'];
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('core.users.manage');

        $user = User::query()->findOrFail($id);

        if ($user->is(auth()->user())) {
            $this->dispatch('toast', type: 'error', message: 'You cannot deactivate your own account.');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->dispatch('toast', message: $user->is_active ? "{$user->name} activated." : "{$user->name} deactivated.");
    }

    public function render(): View
    {
        $query = User::query()->with('roles:id,name');

        if ($this->status !== '') {
            $query->where('is_active', $this->status === 'active');
        }

        $users = $this->applySorting($this->applySearch($query, ['name', 'email']), 'name')
            ->paginate($this->perPage);

        return view('core::livewire.users.index', ['users' => $users]);
    }
}
