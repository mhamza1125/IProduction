<?php

use App\Support\Printing\Printer;
use Illuminate\Support\Facades\File;

it('renders the print layout as a PDF', function () {
    $this->actingAs(admin());

    $pdf = app(Printer::class)->pdf('test-print', ['title' => 'TD-26-0001', 'forPdf' => true])->output();

    expect(substr($pdf, 0, 4))->toBe('%PDF');
});

it('writes a compressed database backup and prunes old ones', function () {
    $dir = storage_path('framework/testing/backups');
    File::deleteDirectory($dir);
    File::ensureDirectoryExists($dir);
    config(['iproduction.backup.path' => $dir]);

    File::put($dir.'/old.sql.gz', 'x');
    touch($dir.'/old.sql.gz', now()->subDays(30)->getTimestamp());

    $this->artisan('db:backup', ['--keep' => 14])->assertSuccessful();

    $files = File::glob($dir.'/*.sql.gz');
    expect($files)->toHaveCount(1)
        ->and(gzdecode(File::get($files[0])))->toContain('CREATE TABLE `users`');

    File::deleteDirectory($dir);
})->skip(fn () => trim((string) shell_exec('command -v mysqldump')) === '', 'mysqldump is not installed');
