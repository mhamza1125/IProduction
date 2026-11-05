<?php

use App\Support\Modules\ModuleRegistry;
use Spatie\Permission\Models\Permission;

it('blocks users without permission from admin pages', function (string $url) {
    $this->actingAs(userWith())->get($url)->assertForbidden();
})->with(['/admin/users', '/admin/roles', '/admin/currencies', '/admin/settings/company']);

it('lets a Super Admin open every admin page', function (string $url) {
    $this->actingAs(superAdmin())->get($url)->assertOk();
})->with(['/admin/users', '/admin/users/create', '/admin/roles', '/admin/roles/create', '/admin/currencies', '/admin/settings/company']);

it('allows read-only access with only the view permission', function () {
    $this->actingAs(userWith(['core.users.view']));

    $this->get('/admin/users')->assertOk()->assertDontSee('New user');
    $this->get('/admin/users/create')->assertForbidden();
});

it('only shows sidebar links the user is allowed to open', function () {
    $registry = app(ModuleRegistry::class);

    $labels = fn ($user) => collect($registry->navigationFor($user))->flatten()->pluck('label')->all();

    expect($labels(userWith()))->toBe([])
        ->and($labels(userWith(['core.users.view'])))->toBe(['Users'])
        ->and($labels(superAdmin()))->toContain('Users', 'Roles', 'Currencies', 'Company settings');
});

it('protects the Horizon dashboard', function () {
    $this->actingAs(userWith())->get('/horizon')->assertForbidden();
});

it('creates every declared permission when syncing', function () {
    $declared = array_keys(app(ModuleRegistry::class)->permissions());

    expect(Permission::pluck('name')->sort()->values()->all())->toBe($declared);
});
