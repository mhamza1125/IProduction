<?php

namespace App\Modules\Core\Livewire\Users;

use App\Models\User;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Users')]
class Index extends Component
{
    use WithDataTable, WithExport;

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
        $this->authorize('users.edit');

        $user = User::query()->findOrFail($id);

        if ($user->is(auth()->user())) {
            $this->dispatch('toast', type: 'error', message: 'You cannot deactivate your own account.');

            return;
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->dispatch('toast', message: $user->is_active ? "{$user->name} activated." : "{$user->name} deactivated.");
    }

    public function sendResetLink(int $id): void
    {
        $this->authorize('users.edit');

        $user = User::query()->findOrFail($id);
        $status = Password::sendResetLink(['email' => $user->email]);

        activity('users')->performedOn($user)->event('password_reset_sent')->log('Sent a password reset link');

        $this->dispatch('toast', type: $status === Password::RESET_LINK_SENT ? 'success' : 'error', message: __($status));
    }

    /**
     * @return Builder<User>
     */
    private function query(): Builder
    {
        $query = User::query()->with('roles:id,name');

        if ($this->status !== '') {
            $query->where('is_active', $this->status === 'active');
        }

        return $this->applySearch($query, ['name', 'email', 'username']);
    }

    protected function exportPermission(): string
    {
        return 'users.export';
    }

    /**
     * @return Builder<User>
     */
    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('name');
    }

    /**
     * @return array<string, \Closure(User): mixed>
     */
    protected function exportColumns(): array
    {
        return [
            'Name' => fn (User $u) => $u->name,
            'Email' => fn (User $u) => $u->email,
            'Username' => fn (User $u) => $u->username,
            'Roles' => fn (User $u) => $u->roles->pluck('name')->implode(', '),
            'Active' => fn (User $u) => $u->is_active ? 'Yes' : 'No',
            'Last sign-in' => fn (User $u) => $u->last_login_at?->format('d-m-Y H:i'),
        ];
    }

    public function render(): View
    {
        $users = $this->applySorting($this->query(), 'name')->paginate($this->perPage);

        return view('core::livewire.users.index', ['users' => $users]);
    }
}
