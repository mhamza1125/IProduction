<?php

use App\Models\User;
use App\Modules\Core\Livewire\Roles\Form;
use App\Modules\Core\Livewire\Roles\Index;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('creates a role with permissions', function () {
    $this->actingAs(admin());

    Livewire::test(Form::class)
        ->set('name', 'Costing Clerk')
        ->set('permissions', ['currencies.edit'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::findByName('Costing Clerk');
    expect($role->hasPermissionTo('currencies.edit'))->toBeTrue();

    $user = User::factory()->create()->assignRole('Costing Clerk');
    expect($user->can('currencies.edit'))->toBeTrue()
        ->and($user->can('users.edit'))->toBeFalse();
});

it('rejects unknown permissions and the reserved Admin name', function () {
    $this->actingAs(admin());

    Livewire::test(Form::class)
        ->set('name', 'Admin')
        ->set('permissions', ['made.up.permission'])
        ->call('save')
        ->assertHasErrors(['name', 'permissions.0']);
});

it('does not allow editing or deleting the Admin role', function () {
    $this->actingAs(admin());
    $role = Role::findByName('Admin');

    $this->get(route('core.roles.edit', $role))->assertForbidden();

    Livewire::test(Index::class)->call('delete', $role->id);
    expect(Role::where('name', 'Admin')->exists())->toBeTrue();
});

it('refuses to delete a role that still has users', function () {
    $this->actingAs(admin());
    $role = Role::create(['name' => 'Supervisor']);
    User::factory()->create()->assignRole($role);

    Livewire::test(Index::class)->call('delete', $role->id);
    expect(Role::where('name', 'Supervisor')->exists())->toBeTrue();

    $role->users()->detach();
    Livewire::test(Index::class)->call('delete', $role->id);
    expect(Role::where('name', 'Supervisor')->exists())->toBeFalse();
});
