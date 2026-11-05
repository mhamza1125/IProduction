<?php

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\TestModule;
use Tests\TestCase;

/*
| Feature tests run against MySQL (database "iproduction_test", see phpunit.xml)
| so they behave like production: decimals, locking and unique indexes.
| TestCase refuses to run against any database not named *_test.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // A sample module with a "test_document" type for the posting framework tests.
        app(ModuleRegistry::class)->add(new TestModule(app()));
        Artisan::call('permissions:sync');
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
| Concurrency tests commit real transactions on two connections, so they use
| truncation instead of RefreshDatabase's wrapping transaction.
*/
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->beforeEach(function () {
        app(ModuleRegistry::class)->add(new TestModule(app()));
        Artisan::call('permissions:sync');
    })
    ->in('Concurrency');

function admin(): User
{
    // fresh() loads every column, as a real request would.
    return User::factory()->create()->fresh()->assignRole(SyncPermissions::ADMIN);
}

/**
 * @param  list<string>  $permissions
 */
function userWith(array $permissions = []): User
{
    $user = User::factory()->create()->fresh();
    $user->givePermissionTo($permissions);

    return $user;
}
