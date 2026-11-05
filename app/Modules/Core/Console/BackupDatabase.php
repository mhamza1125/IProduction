<?php

namespace App\Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Daily MySQL backup (scheduled in routes/console.php): a gzipped mysqldump in
 * storage/app/private/backups, keeping the last N days. Copy the folder off
 * the server (or point BACKUP_PATH at a mounted drive) so a disk failure does
 * not take the backups with it.
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
            $this->error('Backup failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $this->info('Backup written to '.$file);
        $this->prune($dir, (int) ($this->option('keep') ?? config('iproduction.backup.keep_days')));

        return self::SUCCESS;
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
