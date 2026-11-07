<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Permissions review: every screen, print and export route of the business
 * modules is protected by a permission (can:...) on top of being signed in.
 */
it('protects every module route with a permission', function () {
    // Attachment downloads check the parent document's view permission in the controller.
    $open = ['dashboard', 'core.attachments.download'];
    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_contains((string) $r->getActionName(), 'App\\Modules\\') || str_starts_with((string) $r->getName(), 'reports.'))
        ->reject(fn ($r) => in_array($r->getName(), $open, true))
        ->filter(fn ($r) => ! collect($r->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'can:')))
        ->map(fn ($r) => $r->uri())->values()->all();

    expect($unprotected)->toBe([]);
});

it('restores the latest backup into a scratch database and finds it matches', function () {
    // No seeding: RefreshDatabase keeps test rows in an open transaction that
    // mysqldump cannot see, so row counts would differ for a reason unrelated to backups.
    $dir = storage_path('framework/testing/backups-verify');
    config(['iproduction.backup.path' => $dir]);
    File::deleteDirectory($dir);

    $this->artisan('db:verify-backup')->assertFailed();
    $this->artisan('db:backup')->assertSuccessful();
    $this->artisan('db:verify-backup')->expectsOutputToContain('restores cleanly')->assertSuccessful();

    File::deleteDirectory($dir);
})->skip(fn () => ! is_executable('/usr/bin/mysql'), 'mysql client not installed');
