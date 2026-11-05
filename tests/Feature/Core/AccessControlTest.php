<?php

use App\Support\Modules\ModuleRegistry;
use Spatie\Permission\Models\Permission;

it('blocks users without permission from admin pages', function (string $url) {
    $this->actingAs(userWith())->get($url)->assertForbidden();
})->with(['/settings/users', '/settings/roles', '/settings/currencies', '/settings/company', '/settings/numbering', '/settings/audit-log', '/settings/approvals', '/settings/period-lock']);

it('lets a Admin open every admin page', function (string $url) {
    $this->actingAs(admin())->get($url)->assertOk();
})->with(['/settings/users', '/settings/users/create', '/settings/roles', '/settings/roles/create', '/settings/currencies', '/settings/company', '/settings/numbering', '/settings/audit-log', '/settings/approvals', '/settings/period-lock']);

it('allows read-only access with only the view permission', function () {
    $this->actingAs(userWith(['users.view']));

    $this->get('/settings/users')->assertOk()->assertDontSee('New user');
    $this->get('/settings/users/create')->assertForbidden();
});

it('only shows sidebar links the user is allowed to open', function () {
    $registry = app(ModuleRegistry::class);

    $labels = fn ($user) => collect($registry->navigationFor($user))->flatten()->pluck('label')->all();

    expect($labels(userWith()))->toBe([])
        ->and($labels(userWith(['users.view'])))->toBe(['Users'])
        ->and($labels(admin()))->toContain('Users', 'Roles', 'Currencies', 'Company', 'Numbering', 'Audit Log');
});

it('protects the Horizon dashboard', function () {
    $this->actingAs(userWith())->get('/horizon')->assertForbidden();
});

it('creates every declared permission when syncing', function () {
    $declared = array_keys(app(ModuleRegistry::class)->permissions());

    expect(Permission::pluck('name')->sort()->values()->all())->toBe($declared);
});
