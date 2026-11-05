<?php

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/*
| Feature tests run against MySQL (database "iproduction_test", see phpunit.xml)
| so they behave like production: decimals, locking and unique indexes.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Artisan::call('permissions:sync'))
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

function superAdmin(): User
{
    return User::factory()->create()->assignRole(SyncPermissions::SUPER_ADMIN);
}

/**
 * @param  list<string>  $permissions
 */
function userWith(array $permissions = []): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permissions);

    return $user;
}
