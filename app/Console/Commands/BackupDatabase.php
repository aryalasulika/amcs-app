<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Periodic MySQL backup with retention pruning (NFR-07).
 */
class BackupDatabase extends Command
{
    protected $signature = 'amcs:backup-database {--keep=30 : Jumlah hari berkas backup disimpan}';

    protected $description = 'Membuat backup MySQL dan menghapus backup lama sesuai retensi';

    public function handle(): int
    {
        $directory = storage_path('app/backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $database = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');

        $filename = $directory.DIRECTORY_SEPARATOR.'amcs_'.CarbonImmutable::now()->format('Ymd_His').'.sql';

        $command = [
            'mysqldump',
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--single-transaction',
            '--routines',
            '--result-file='.$filename,
            $database,
        ];

        $this->info('Menjalankan mysqldump...');

        $result = Process::timeout(600)
            ->env($password ? ['MYSQL_PWD' => $password] : [])
            ->run($command);

        if (! $result->successful()) {
            $this->error('Backup gagal: '.$result->errorOutput());

            return self::FAILURE;
        }

        $this->info('Backup tersimpan: '.$filename);

        $keep = (int) $this->option('keep');
        $threshold = CarbonImmutable::now()->subDays($keep)->getTimestamp();
        $removed = 0;

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.sql') as $file) {
            if (filemtime($file) < $threshold) {
                @unlink($file);
                $removed++;
            }
        }

        $this->info("Backup lama dihapus: {$removed}.");

        return self::SUCCESS;
    }
}
