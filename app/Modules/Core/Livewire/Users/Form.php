<?php

namespace App\Modules\Core\Livewire\Users;

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Form extends Component
{
    #[Locked]
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $is_active = true;

    /** @var list<string> */
    public array $roles = [];

    public function mount(?User $user = null): void
    {
        if ($user?->exists) {
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->is_active = $user->is_active;
            $this->roles = $user->roles->pluck('name')->values()->all();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user?->id)],
            'password' => [$this->user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    public function save(): void
    {
        $this->authorize('core.users.manage');

        $data = $this->validate();
        $editingSelf = $this->user?->is(auth()->user()) ?? false;

        if ($editingSelf && ! $data['is_active']) {
            $this->addError('is_active', 'You cannot deactivate your own account.');

            return;
        }

        if ($editingSelf && $this->user->hasRole(SyncPermissions::SUPER_ADMIN) && ! in_array(SyncPermissions::SUPER_ADMIN, $data['roles'], true)) {
            $this->addError('roles', 'You cannot remove the Super Admin role from yourself.');

            return;
        }

        DB::transaction(function () use ($data) {
            $user = $this->user ?? new User;
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'],
            ]);

            if ($data['password'] !== null && $data['password'] !== '') {
                $user->password = $data['password'];
            }

            $user->save();
            $user->syncRoles($data['roles']);
        });

        session()->flash('toast', $this->user ? 'User updated.' : 'User created.');

        $this->redirectRoute('core.users.index', navigate: true);
    }

    public function render(): View
    {
        return view('core::livewire.users.form', [
            'allRoles' => Role::query()->orderBy('name')->pluck('name'),
        ])->title($this->user ? 'Edit user' : 'New user');
    }
}
