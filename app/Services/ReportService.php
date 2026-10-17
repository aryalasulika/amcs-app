<?php

namespace App\Services;

use App\Enums\MovementState;
use App\Models\Movement;
use App\Models\MovementLog;
use App\Models\ParkingStand;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Reporting, filtering and export data (FR-13, FR-14).
 */
class ReportService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function historyQuery(array $filters = []): Builder
    {
        return Movement::query()
            ->with(['parkingStand', 'airline', 'aircraftType', 'creator', 'updater'])
            ->when($filters['date'] ?? null, fn (Builder $q, $date) => $q->whereDate('report_date', $date))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $from) => $q->whereDate('report_date', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $to) => $q->whereDate('report_date', '<=', $to))
            ->when($filters['flight_number'] ?? null, fn (Builder $q, $flight) => $q->where('flight_number', 'like', '%'.strtoupper($flight).'%'))
            ->when($filters['parking_stand_id'] ?? null, fn (Builder $q, $stand) => $q->where('parking_stand_id', $stand))
            ->when($filters['airline_id'] ?? null, fn (Builder $q, $airline) => $q->where('airline_id', $airline))
            ->when($filters['state'] ?? null, fn (Builder $q, $state) => $q->where('state', $state))
            ->when($filters['flight_type'] ?? null, fn (Builder $q, $type) => $q->where('flight_type', $type))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $user) => $q->where(fn (Builder $inner) => $inner
                ->where('created_by', $user)
                ->orWhere('updated_by', $user)))
            ->orderByDesc('report_date')
            ->orderByDesc('updated_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function history(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->historyQuery($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Daily recap (FR-14).
     *
     * @return array<string, mixed>
     */
    public function dailyReport(string $date): array
    {
        $movements = Movement::query()
            ->whereDate('report_date', $date)
            ->with(['parkingStand', 'airline', 'aircraftType'])
            ->orderBy('flight_number')
            ->get();

        $perStand = $this->perStandUsage($movements);
        $turnarounds = $this->turnaroundMinutes($movements);

        return [
            'date' => $date,
            'totals' => [
                'movements' => $movements->count(),
                'delays' => $movements->where('delay_minutes', '>', 0)->count(),
                'ron' => $movements->where('state', MovementState::RON)->count(),
                'active' => $movements->filter(fn (Movement $m) => $m->isActive())->count(),
                'total_delay_minutes' => (int) $movements->sum('delay_minutes'),
                'average_turnaround_minutes' => $turnarounds->isEmpty() ? null : (int) round($turnarounds->avg()),
            ],
            'per_stand' => $perStand,
            'movements' => $movements,
        ];
    }

    /**
     * Monthly recap with per-day breakdown (FR-14).
     *
     * @return array<string, mixed>
     */
    public function monthlyReport(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        $movements = Movement::query()
            ->whereBetween('report_date', [$start->toDateString(), $end->toDateString()])
            ->with(['parkingStand', 'airline'])
            ->get();

        $byDay = $movements
            ->groupBy(fn (Movement $m) => $m->report_date->toDateString())
            ->map(fn (Collection $items, string $day) => [
                'date' => $day,
                'movements' => $items->count(),
                'delays' => $items->where('delay_minutes', '>', 0)->count(),
                'ron' => $items->where('state', MovementState::RON)->count(),
            ])
            ->sortKeys()
            ->values();

        $turnarounds = $this->turnaroundMinutes($movements);

        return [
            'period' => ['year' => $year, 'month' => $month, 'label' => $start->translatedFormat('F Y')],
            'totals' => [
                'movements' => $movements->count(),
                'delays' => $movements->where('delay_minutes', '>', 0)->count(),
                'ron' => $movements->where('state', MovementState::RON)->count(),
                'total_delay_minutes' => (int) $movements->sum('delay_minutes'),
                'average_turnaround_minutes' => $turnarounds->isEmpty() ? null : (int) round($turnarounds->avg()),
            ],
            'per_stand' => $this->perStandUsage($movements),
            'by_day' => $byDay,
        ];
    }

    /**
     * Totals for the report summary cards, honouring the active filters
     * (FR-13/FR-14) instead of always returning an all-time recap.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function filteredTotals(array $filters = []): array
    {
        $movements = $this->historyQuery($filters)->get();
        $turnarounds = $this->turnaroundMinutes($movements);

        return [
            'movements' => $movements->count(),
            'delays' => $movements->where('delay_minutes', '>', 0)->count(),
            'ron' => $movements->where('state', MovementState::RON)->count(),
            'active' => $movements->filter(fn (Movement $m) => $m->isActive())->count(),
            'total_delay_minutes' => (int) $movements->sum('delay_minutes'),
            'average_turnaround_minutes' => $turnarounds->isEmpty() ? null : (int) round($turnarounds->avg()),
        ];
    }

    /**
     * Yearly / all-time recap used by the "Rekap Tahunan" sheet (FR-14).
     *
     * @return array<int, array<string, mixed>>
     */
    public function yearlySummary(?int $year = null): array
    {
        $query = Movement::query();

        if ($year) {
            $query->whereYear('report_date', $year);
        }

        $movements = $query->get();

        return $movements
            ->groupBy(fn (Movement $m) => $m->report_date->format('Y-m'))
            ->map(function (Collection $items, string $period) {
                [$y, $m] = explode('-', $period);

                return [
                    'period' => $period,
                    'label' => CarbonImmutable::create((int) $y, (int) $m, 1)->translatedFormat('F Y'),
                    'year' => (int) $y,
                    'month' => (int) $m,
                    'movements' => $items->count(),
                    'delays' => $items->where('delay_minutes', '>', 0)->count(),
                    'ron' => $items->where('state', MovementState::RON)->count(),
                    'total_delay_minutes' => (int) $items->sum('delay_minutes'),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * Chronological activity log for the AMC dashboard table (FR-12/13).
     *
     * @param  array<string, mixed>  $filters
     */
    public function activityLog(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        return MovementLog::query()
            ->with('user')
            ->when($filters['date'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', $date))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['stand_code'] ?? null, fn (Builder $q, $stand) => $q->where('stand_code', $stand))
            ->when($filters['flight_number'] ?? null, fn (Builder $q, $flight) => $q->where('flight_number', 'like', '%'.strtoupper($flight).'%'))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Rows ready for Excel/CSV export (FR-14).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, mixed>>
     */
    public function exportRows(array $filters = []): array
    {
        $rows = [[
            'WAKTU',
            'TANGGAL LAPORAN',
            'STAND',
            'FLIGHT',
            'MASKAPAI',
            'RUTE (ICAO)',
            'JENIS',
            'TIPE AC',
            'REGISTRASI',
            'STATUS',
            'ETA',
            'ATA',
            'ETD',
            'ATD',
            'DELAY (MENIT)',
            'ALASAN DELAY',
            'DIBUAT OLEH',
        ]];

        $this->historyQuery($filters)
            ->orderBy('report_date')
            ->orderBy('created_at')
            ->chunk(500, function (Collection $movements) use (&$rows) {
                foreach ($movements as $movement) {
                    $rows[] = [
                        $movement->updated_at?->format('d/m/Y H:i'),
                        $movement->report_date?->format('d/m/Y'),
                        $movement->parkingStand?->code ?? '-',
                        $movement->flight_number,
                        $movement->airline_name ?? '-',
                        ($movement->origin ?? '-').' -> '.($movement->destination ?? '-'),
                        $movement->flight_type,
                        $movement->aircraft_type_name ?? '-',
                        $movement->registration ?? '-',
                        $movement->state instanceof MovementState ? $movement->state->label() : $movement->state,
                        $movement->eta ?? '-',
                        $movement->ata ?? '-',
                        $movement->etd ?? '-',
                        $movement->atd ?? '-',
                        $movement->delay_minutes,
                        $movement->delay_reason ?? '-',
                        $movement->creator?->name ?? '-',
                    ];
                }
            });

        return $rows;
    }

    /**
     * Per-stand usage counts.
     *
     * @param  Collection<int, Movement>  $movements
     * @return array<int, array<string, mixed>>
     */
    protected function perStandUsage(Collection $movements): array
    {
        $stands = ParkingStand::orderBy('id')->get();

        return $stands->map(function (ParkingStand $stand) use ($movements) {
            $items = $movements->where('parking_stand_id', $stand->id);

            return [
                'stand_id' => $stand->id,
                'stand' => $stand->code,
                'name' => $stand->name,
                'movements' => $items->count(),
                'delays' => $items->where('delay_minutes', '>', 0)->count(),
                'ron' => $items->where('state', MovementState::RON)->count(),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Movement>  $movements
     * @return Collection<int, int>
     */
    protected function turnaroundMinutes(Collection $movements): Collection
    {
        return $movements
            ->filter(fn (Movement $m) => $m->ata && $m->atd)
            ->map(function (Movement $m) {
                $date = $m->report_date?->toDateString() ?? CarbonImmutable::now()->toDateString();
                $arrival = $this->combine($date, $m->ata);
                $departure = $this->combine($date, $m->atd);

                if (! $arrival || ! $departure) {
                    return null;
                }

                if ($departure->lessThan($arrival)) {
                    $departure = $departure->addDay();
                }

                return (int) $arrival->diffInMinutes($departure);
            })
            ->filter(fn ($value) => $value !== null)
            ->values();
    }

    protected function combine(string $date, string $time): ?CarbonInterface
    {
        try {
            return CarbonImmutable::parse($date.' '.$time);
        } catch (\Throwable) {
            return null;
        }
    }
}
