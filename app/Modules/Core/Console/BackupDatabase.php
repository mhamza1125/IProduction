<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Services\Alerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Daily MySQL backup (scheduled in routes/console.php): a gzipped mysqldump in
 * storage/app/private/backups, keeping the last N days. Set BACKUP_COPY_PATH to
 * a second disk (another drive, USB or network share mounted on this machine)
 * so a disk failure does not take the backups with it.
 *
 * Any failure raises an alert in the app's notification bell (no email).
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep= : Days of backups to keep (default from config)}';

    protected $description = 'Dump the database to a gzipped SQL file and delete old backups';

    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));

        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error('Backups are only supported for MySQL.');

            return self::FAILURE;
        }

        $dir = (string) config('iproduction.backup.path');
        File::ensureDirectoryExists($dir, 0750);

        $file = $dir.'/'.$connection['database'].'-'.now()->format('Y-m-d-His').'.sql.gz';

        // Password goes through the environment, not the command line, so it
        // does not show up in the process list.
        $result = Process::timeout(3600)
            ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
            ->run(['bash', '-c', sprintf(
                'set -o pipefail; mysqldump --single-transaction --quick --routines --no-tablespaces -h %s -P %s -u %s %s | gzip > %s',
                escapeshellarg((string) $connection['host']),
                escapeshellarg((string) $connection['port']),
                escapeshellarg((string) $connection['username']),
                escapeshellarg((string) $connection['database']),
                escapeshellarg($file),
            )]);

        if ($result->failed()) {
            File::delete($file);

            return $this->failWithAlert('Backup failed', trim($result->errorOutput()));
        }

        $this->info('Backup written to '.$file);
        $keep = (int) ($this->option('keep') ?? config('iproduction.backup.keep_days'));
        $this->prune($dir, $keep);

        $copyDir = (string) config('iproduction.backup.copy_path');
        if ($copyDir !== '') {
            try {
                File::ensureDirectoryExists($copyDir, 0750);
                $copy = $copyDir.'/'.basename($file);
                if (! File::copy($file, $copy) || File::size($copy) !== File::size($file)) {
                    throw new \RuntimeException("could not write {$copy}");
                }
                $this->info('Copied to '.$copy);
                $this->prune($copyDir, $keep);
            } catch (\Throwable $e) {
                return $this->failWithAlert('Backup copy failed', 'The backup was made on this disk but not copied to '.$copyDir.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function failWithAlert(string $title, string $detail): int
    {
        $this->error("{$title}: {$detail}");
        app(Alerts::class)->toAdmins($title, $detail.' Run "php artisan db:backup" on the server to retry.', key: 'backup');

        return self::FAILURE;
    }

    private function prune(string $dir, int $keepDays): void
    {
        $cutoff = now()->subDays(max(1, $keepDays))->getTimestamp();

        foreach (File::glob($dir.'/*.sql.gz') as $old) {
            if (File::lastModified($old) < $cutoff) {
                File::delete($old);
                $this->line('Deleted old backup '.basename($old));
            }
        }
    }
}
