<?php

namespace App\Console\Commands;

use App\Models\MovementLog;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Archive-and-prune retention for the activity log (FR-15).
 *
 * Old movement_logs are exported to a JSON archive before being removed from
 * the database. The immutable audit_logs table is never touched.
 */
class ArchiveActivityLogs extends Command
{
    protected $signature = 'amcs:archive-activity {--days=1095 : Umur minimum (hari) sebelum log diarsipkan} {--dry-run}';

    protected $description = 'Mengarsipkan log aktivitas lama ke berkas lalu menghapusnya dari database';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = CarbonImmutable::now()->subDays($days);

        $query = MovementLog::query()->where('created_at', '<', $cutoff);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Tidak ada log aktivitas yang perlu diarsipkan.');

            return self::SUCCESS;
        }

        $directory = storage_path('app/archive');
        File::ensureDirectoryExists($directory);
        $file = $directory.DIRECTORY_SEPARATOR.'movement_logs_'.CarbonImmutable::now()->format('Ymd_His').'.json';

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] {$total} log lebih lama dari {$cutoff->toDateString()} akan diarsipkan ke {$file}.");

            return self::SUCCESS;
        }

        $handle = fopen($file, 'w');
        fwrite($handle, "[\n");
        $first = true;
        $archived = 0;

        $query->orderBy('id')->chunk(1000, function ($logs) use ($handle, &$first, &$archived) {
            foreach ($logs as $log) {
                $line = json_encode($log->toArray(), JSON_UNESCAPED_UNICODE);
                fwrite($handle, ($first ? '' : ",\n").$line);
                $first = false;
                $archived++;
            }
        });

        fwrite($handle, "\n]\n");
        fclose($handle);

        MovementLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Diarsipkan {$archived} log ke {$file} dan dihapus dari database.");

        return self::SUCCESS;
    }
}
