<?php

use App\Models\User;
use App\Modules\Core\Livewire\Roles\Form;
use App\Modules\Core\Livewire\Roles\Index;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('creates a role with permissions', function () {
    $this->actingAs(superAdmin());

    Livewire::test(Form::class)
        ->set('name', 'Accountant')
        ->set('permissions', ['core.currencies.manage'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::findByName('Accountant');
    expect($role->hasPermissionTo('core.currencies.manage'))->toBeTrue();

    $user = User::factory()->create()->assignRole('Accountant');
    expect($user->can('core.currencies.manage'))->toBeTrue()
        ->and($user->can('core.users.manage'))->toBeFalse();
});

it('rejects unknown permissions and the reserved Super Admin name', function () {
    $this->actingAs(superAdmin());

    Livewire::test(Form::class)
        ->set('name', 'Super Admin')
        ->set('permissions', ['made.up.permission'])
        ->call('save')
        ->assertHasErrors(['name', 'permissions.0']);
});

it('does not allow editing or deleting the Super Admin role', function () {
    $this->actingAs(superAdmin());
    $role = Role::findByName('Super Admin');

    $this->get(route('core.roles.edit', $role))->assertForbidden();

    Livewire::test(Index::class)->call('delete', $role->id);
    expect(Role::where('name', 'Super Admin')->exists())->toBeTrue();
});

it('refuses to delete a role that still has users', function () {
    $this->actingAs(superAdmin());
    $role = Role::create(['name' => 'Supervisor']);
    User::factory()->create()->assignRole($role);

    Livewire::test(Index::class)->call('delete', $role->id);
    expect(Role::where('name', 'Supervisor')->exists())->toBeTrue();

    $role->users()->detach();
    Livewire::test(Index::class)->call('delete', $role->id);
    expect(Role::where('name', 'Supervisor')->exists())->toBeFalse();
});
