<?php

namespace App\Console\Commands;

use App\Models\Movement;
use App\Models\MovementLog;
use App\Models\ParkingStand;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off migration helper: imports a Firebase Realtime Database JSON export
 * of /apron_stands and /logs into the MySQL operational tables (PRD §12).
 */
class ImportFirebaseData extends Command
{
    protected $signature = 'amcs:import-firebase {file : Path to the JSON export} {--dry-run}';

    protected $description = 'Import data aktif dan histori dari export Firebase ke MySQL';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            $this->error('Berkas bukan JSON yang valid.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $stands = $payload['apron_stands'] ?? [];
        $logs = $payload['logs'] ?? [];

        $this->info(($dryRun ? '[DRY RUN] ' : '').'Memproses '.count($stands).' stand dan '.count($logs).' log.');

        $movements = 0;
        $logRows = 0;

        DB::transaction(function () use ($stands, $logs, $dryRun, &$movements, &$logRows) {
            foreach ($stands as $standKey => $data) {
                if (! is_array($data) || ($data['state'] ?? 'VACANT') === 'VACANT') {
                    continue;
                }

                $stand = ParkingStand::where('code', 'STAND-'.$standKey)->orWhere('id', $standKey)->first();

                if ($dryRun) {
                    $movements++;

                    continue;
                }

                Movement::create([
                    'report_date' => CarbonImmutable::now()->toDateString(),
                    'flight_number' => $data['flight'] ?? ('LEGACY-'.$standKey),
                    'airline_name' => $data['airlineLabel'] ?? ($data['airline'] ?? null),
                    'registration' => $data['reg'] ?? null,
                    'aircraft_type_name' => $data['ac'] ?? null,
                    'flight_type' => $data['flightType'] ?? 'SCHEDULED',
                    'origin' => $data['origin'] ?? null,
                    'destination' => $data['dest'] ?? null,
                    'parking_stand_id' => $stand?->id,
                    'state' => in_array($data['state'] ?? '', ['ENTERING', 'ON', 'DELAY', 'RON'], true) ? $data['state'] : 'ENTERING',
                    'eta' => $this->timeOrNull($data['eta'] ?? null),
                    'ata' => $this->timeOrNull($data['ata'] ?? null),
                    'etd' => $this->timeOrNull($data['etd'] ?? null),
                    'atd' => $this->timeOrNull($data['atd'] ?? null),
                    'runway' => $data['rwy'] ?? null,
                    'taxiway' => $data['twy'] ?? null,
                    'delay_minutes' => (int) ($data['delay'] ?? 0),
                    'delay_reason' => $data['reason'] ?? null,
                ]);
                $movements++;
            }

            foreach ($logs as $logKey => $log) {
                if (! is_array($log)) {
                    continue;
                }

                if ($dryRun) {
                    $logRows++;

                    continue;
                }

                MovementLog::create([
                    'movement_id' => null,
                    'stand_code' => isset($log['stand']) ? (string) $log['stand'] : null,
                    'flight_number' => $log['flight'] ?? null,
                    'action' => $log['action'] ?? 'IMPORTED',
                    'payload' => $log,
                    'created_at' => $this->parseLegacyTime($log['time'] ?? null),
                ]);
                $logRows++;
            }
        });

        $this->info("Selesai. Pergerakan aktif diimpor: {$movements}. Log histori diimpor: {$logRows}.");

        return self::SUCCESS;
    }

    protected function timeOrNull(?string $value): ?string
    {
        if (! $value || $value === '-' || ! preg_match('/^\d{1,2}:\d{2}/', $value)) {
            return null;
        }

        return substr($value, 0, 5);
    }

    protected function parseLegacyTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('d/m/Y H:i', $value)->toDateTimeString();
        } catch (\Throwable) {
            return CarbonImmutable::now()->toDateTimeString();
        }
    }
}
