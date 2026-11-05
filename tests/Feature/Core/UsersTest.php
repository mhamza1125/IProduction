<?php

use App\Models\User;
use App\Modules\Core\Livewire\Users\Form;
use App\Modules\Core\Livewire\Users\Index;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

it('creates a user with roles', function () {
    $this->actingAs(admin());
    Role::create(['name' => 'Store Keeper']);

    Livewire::test(Form::class)
        ->set('name', 'Ali Raza')
        ->set('email', 'ali@example.com')
        ->set('password', 'Secret-pass-123')
        ->set('password_confirmation', 'Secret-pass-123')
        ->set('roles', ['Store Keeper'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('core.users.index'));

    $user = User::where('email', 'ali@example.com')->firstOrFail();
    expect($user->hasRole('Store Keeper'))->toBeTrue()
        ->and(Hash::check('Secret-pass-123', $user->password))->toBeTrue();
});

it('validates the user form', function () {
    $this->actingAs(admin());
    $existing = User::factory()->create();

    Livewire::test(Form::class)
        ->set('email', $existing->email)
        ->set('roles', ['Does Not Exist'])
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'email' => 'unique', 'password' => 'required', 'roles.0']);
});

it('keeps the password when it is left blank on edit', function () {
    $this->actingAs(admin());
    $user = User::factory()->create();
    $hash = $user->password;

    Livewire::test(Form::class, ['user' => $user])
        ->set('name', 'Renamed')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh())->name->toBe('Renamed')->password->toBe($hash);
});

it('stops admins from deactivating themselves', function () {
    $admin = admin();
    $this->actingAs($admin);

    Livewire::test(Form::class, ['user' => $admin])
        ->set('is_active', false)
        ->call('save')
        ->assertHasErrors('is_active');

    Livewire::test(Index::class)->call('toggleActive', $admin->id);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('deactivates another user from the list', function () {
    $this->actingAs(admin());
    $user = User::factory()->create();

    Livewire::test(Index::class)->call('toggleActive', $user->id);

    expect($user->fresh()->is_active)->toBeFalse();
});

it('searches and sorts the user list', function () {
    $this->actingAs(admin());
    User::factory()->create(['name' => 'Zainab Khan']);
    User::factory()->create(['name' => 'Bilal Ahmed']);

    Livewire::test(Index::class)
        ->set('search', 'zain')
        ->assertSee('Zainab Khan')
        ->assertDontSee('Bilal Ahmed')
        ->set('search', '')
        ->call('sortBy', 'password') // not sortable: ignored
        ->assertSet('sortField', '')
        ->call('sortBy', 'name')
        ->assertSet('sortField', 'name');
});

it('rejects actions from users without the manage permission', function () {
    $this->actingAs(userWith(['users.view']));
    $user = User::factory()->create();

    Livewire::test(Index::class)->call('toggleActive', $user->id)->assertForbidden();
});

it('records changes in the activity log', function () {
    $this->actingAs($admin = admin());
    $user = User::factory()->create();

    $user->update(['name' => 'Changed Name']);

    $activity = Activity::query()->where('subject_id', $user->id)->latest('id')->first();
    expect($activity->description)->toBe('updated')
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['attributes']['name'])->toBe('Changed Name');
});

it('emails a password reset link and exports the list', function () {
    Notification::fake();
    $this->actingAs(admin());
    $user = User::factory()->create();

    Livewire::test(Index::class)->call('sendResetLink', $user->id);
    Notification::assertSentTo($user, ResetPassword::class);

    Livewire::test(Index::class)->call('export', 'csv')->assertFileDownloaded();
});

it('does not let users without users.export download the list', function () {
    $this->actingAs(userWith(['users.view']));

    Livewire::test(Index::class)->call('export', 'xlsx')->assertForbidden();
});
