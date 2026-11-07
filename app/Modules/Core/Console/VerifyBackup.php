<?php

namespace App\Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Backup test (go-live): restores the newest backup into a separate scratch
 * database, compares its tables and row counts of key tables with the live
 * database, then drops the scratch database. The live database is only read.
 */
class VerifyBackup extends Command
{
    protected $signature = 'db:verify-backup {file? : Backup file (default: the newest in the backup folder)}
        {--scratch= : Scratch database to restore into (default: {database}_restore_check)}';

    protected $description = 'Restore a backup into a scratch database and check it matches';

    /** Tables whose row counts must match. */
    private const KEY_TABLES = ['users', 'journals', 'journal_lines', 'stock_movements', 'stock_balances', 'lots'];

    public function handle(): int
    {
        $connection = config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) !== 'mysql') {
            $this->error('Backups are only supported for MySQL.');

            return self::FAILURE;
        }

        $file = $this->argument('file') ?: collect(File::glob(config('iproduction.backup.path').'/*.sql.gz'))->sortByDesc(fn ($f) => File::lastModified($f))->first();
        if (! $file || ! File::exists($file)) {
            $this->error('No backup file found. Run php artisan db:backup first.');

            return self::FAILURE;
        }

        $scratch = (string) ($this->option('scratch') ?: $connection['database'].'_restore_check');
        $env = ['MYSQL_PWD' => (string) ($connection['password'] ?? '')];
        $base = sprintf('mysql -h %s -P %s -u %s', escapeshellarg((string) $connection['host']), escapeshellarg((string) $connection['port']), escapeshellarg((string) $connection['username']));

        try {
            $steps = [
                sprintf('%s -e %s', $base, escapeshellarg("DROP DATABASE IF EXISTS `{$scratch}`; CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")),
                sprintf('set -o pipefail; gunzip -c %s | %s %s', escapeshellarg($file), $base, escapeshellarg($scratch)),
            ];
            foreach ($steps as $step) {
                $result = Process::timeout(3600)->env($env)->run(['bash', '-c', $step]);
                if ($result->failed()) {
                    $this->error('Restore failed: '.trim($result->errorOutput()));
                    $this->line("The database user needs all privileges on `{$scratch}` (GRANT ALL ON `{$scratch}`.* TO ...).");

                    return self::FAILURE;
                }
            }

            // The live side is read through the app connection; the scratch side through
            // the mysql client, so this connection never holds locks on the scratch database
            // (which would block dropping it).
            $live = $connection['database'];
            $tableCount = fn (string $db) => "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$db}'";
            $liveCounts = [(int) DB::selectOne(str_replace('COUNT(*)', 'COUNT(*) AS c', $tableCount($live)))->c];
            foreach (self::KEY_TABLES as $table) {
                $liveCounts[] = (int) DB::selectOne("SELECT COUNT(*) AS c FROM `{$live}`.`{$table}`")->c;
            }
            $sql = implode(' UNION ALL ', array_merge([$tableCount($scratch)], array_map(fn ($t) => "SELECT COUNT(*) FROM `{$scratch}`.`{$t}`", self::KEY_TABLES)));
            $result = Process::timeout(600)->env($env)->run(['bash', '-c', sprintf('%s -N -B -e %s', $base, escapeshellarg($sql))]);
            if ($result->failed()) {
                $this->error('Could not read the restored database: '.trim($result->errorOutput()));

                return self::FAILURE;
            }
            $backupCounts = array_map('intval', preg_split('/\s+/', trim($result->output())) ?: []);

            $problems = [];
            foreach (array_merge(['Tables'], self::KEY_TABLES) as $i => $label) {
                $a = $liveCounts[$i];
                $b = $backupCounts[$i] ?? 0;
                if ($a !== $b) {
                    $problems[] = $i === 0
                        ? "Tables: live {$a}, backup {$b}."
                        : "{$label}: live {$a} rows, backup {$b} (changes since the backup are expected on a busy system).";
                }
            }

            if ($problems !== []) {
                $this->warn('Backup restored but differs:');
                foreach ($problems as $p) {
                    $this->line(' - '.$p);
                }

                return self::FAILURE;
            }
            $this->info('Backup '.basename($file).' restores cleanly and matches the live database.');

            return self::SUCCESS;
        } finally {
            Process::env($env)->run(['bash', '-c', sprintf('%s -e %s', $base, escapeshellarg("DROP DATABASE IF EXISTS `{$scratch}`;"))]);
        }
    }
}
