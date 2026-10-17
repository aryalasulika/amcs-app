<?php

namespace App\Services;

use App\Enums\AircraftCategory;
use App\Enums\MovementState;
use App\Exceptions\OperationException;
use App\Models\AircraftType;
use App\Models\ParkingStand;
use App\Models\StandReservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Stand capacity, availability and recommendation rules (FR-06, FR-07).
 */
class StandAllocationService
{
    /**
     * Relative "size" ranking used when a stand has no explicit aircraft
     * whitelist but declares a capacity category.
     *
     * @var array<string, int>
     */
    protected array $categoryRank = [
        'LIGHT' => 1,
        'TURBOPROP' => 2,
        'PRIVATE_JET' => 3,
        'NARROW_BODY' => 4,
        'WIDE_BODY' => 5,
        // Manually entered types with no reliable category cannot be verified
        // and are treated as the largest category (rejected unless overridden).
        'OTHER' => 6,
    ];

    public function assertOperable(ParkingStand $stand): void
    {
        if (! $stand->active) {
            throw OperationException::make(
                "Parking {$stand->name} sedang nonaktif dan tidak dapat digunakan."
            );
        }
    }

    /**
     * Ensure the aircraft fits the stand capacity (FR-06).
     */
    public function assertCompatible(
        ParkingStand $stand,
        ?AircraftType $type,
        ?string $aircraftName = null,
        ?AircraftCategory $declaredCategory = null,
    ): void {
        $label = $type?->code ?? ($aircraftName ?: 'Tipe pesawat');
        $allowedIds = $stand->aircraftTypes()->pluck('aircraft_types.id');

        if ($allowedIds->isNotEmpty()) {
            if (! $type) {
                throw OperationException::make(
                    "Tipe pesawat \"{$label}\" belum terdaftar pada master data sehingga kapasitas {$stand->name} tidak dapat diverifikasi. "
                    .'Minta supervisor melakukan override bila tetap dilanjutkan.',
                    errors: ['parking_stand_id' => ['Tipe pesawat tidak dikenali untuk stand ini.']]
                );
            }

            if (! $allowedIds->contains($type->id)) {
                throw OperationException::make(
                    "Tipe pesawat {$label} melampaui kapasitas {$stand->name}. "
                    .'Pilih stand lain atau minta supervisor melakukan override.',
                    errors: ['parking_stand_id' => ["{$label} tidak diizinkan di {$stand->name}."]]
                );
            }

            return;
        }

        // Fallback: category based capacity check.
        if ($stand->capacity_category) {
            $category = $type?->category ?? $declaredCategory;
            $aircraftRank = $this->rank($category);
            $standRank = $this->categoryRank[$stand->capacity_category] ?? null;

            if ($aircraftRank !== null && $standRank !== null && $aircraftRank > $standRank) {
                $categoryLabel = $category?->label() ?? 'tidak diketahui';

                throw OperationException::make(
                    "Tipe pesawat {$label} ({$categoryLabel}) melampaui kategori kapasitas {$stand->name} ({$stand->capacity_category}).",
                    errors: ['parking_stand_id' => ["{$label} tidak sesuai kapasitas {$stand->name}."]]
                );
            }
        }
    }

    protected function rank(?AircraftCategory $category): ?int
    {
        if (! $category) {
            return null;
        }

        return $this->categoryRank[$category->value] ?? null;
    }

    /**
     * Reject allocation when another active movement already occupies the stand
     * (FR-06, MVP #3). The caller must hold a row lock on the stand.
     */
    public function assertAvailable(ParkingStand $stand, ?int $ignoreMovementId = null): void
    {
        $query = $stand->movements()
            ->whereIn('state', MovementState::activeValues());

        if ($ignoreMovementId !== null) {
            $query->whereKeyNot($ignoreMovementId);
        }

        $occupant = $query->latest('updated_at')->first();

        if ($occupant) {
            throw OperationException::make(
                "{$stand->name} sedang digunakan oleh penerbangan {$occupant->flight_number}. "
                .'Pilih stand lain atau minta supervisor melakukan override.',
                errors: ['parking_stand_id' => ["{$stand->name} sedang terisi."]]
            );
        }
    }

    /**
     * Reservation windows that overlap the requested window (FR-06 warning).
     *
     * @return Collection<int, StandReservation>
     */
    public function findOverlaps(
        ParkingStand $stand,
        ?CarbonInterface $start,
        ?CarbonInterface $end,
        ?int $ignoreMovementId = null,
    ): Collection {
        if (! $start || ! $end) {
            return collect();
        }

        return $stand->reservations()
            ->where('status', 'ACTIVE')
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->when($ignoreMovementId, fn ($q) => $q->whereKeyNot(
                StandReservation::where('movement_id', $ignoreMovementId)->value('id') ?? 0
            ))
            ->with('movement')
            ->get();
    }

    /**
     * Stands that are active and currently free.
     *
     * @return Collection<int, ParkingStand>
     */
    public function availableStands(): Collection
    {
        return ParkingStand::query()
            ->where('active', true)
            ->with('aircraftTypes')
            ->get()
            ->filter(fn (ParkingStand $stand) => ! $stand->isOccupied())
            ->values();
    }

    /**
     * Rank suitable stands for a movement (FR-07). Informational only – the
     * operator keeps the final decision.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<int, array<string, mixed>>
     */
    public function recommend(array $criteria): array
    {
        $aircraftType = null;
        if (! empty($criteria['aircraft_type_id'])) {
            $aircraftType = AircraftType::find($criteria['aircraft_type_id']);
        }

        $aircraftName = $criteria['aircraft_type_name'] ?? null;
        $declaredCategory = AircraftCategory::tryFrom((string) ($criteria['aircraft_category'] ?? ''));
        $preferredStandId = $criteria['parking_stand_id'] ?? null;
        $start = $this->combineDateTime($criteria['report_date'] ?? null, $criteria['eta'] ?? $criteria['ata'] ?? null);
        $end = $this->combineDateTime($criteria['report_date'] ?? null, $criteria['etd'] ?? null);

        $stands = ParkingStand::query()->where('active', true)->with('aircraftTypes')->get();

        $scored = $stands->map(function (ParkingStand $stand) use ($aircraftType, $aircraftName, $declaredCategory, $preferredStandId, $start, $end) {
            $score = 0;
            $allowed = false;
            $reasons = [];
            $warnings = [];

            try {
                $this->assertCompatible($stand, $aircraftType, $aircraftName, $declaredCategory);
                $allowed = true;
                $score += 50;
                $reasons[] = 'Kapasitas sesuai';
            } catch (OperationException $e) {
                $warnings[] = $e->getMessage();
                $score -= 100;
            }

            $occupied = $stand->isOccupied();
            if ($occupied) {
                $warning = "Stand sedang terisi oleh {$stand->activeMovement()?->flight_number}.";
                $warnings[] = $warning;
                $score -= 120;
            } else {
                $score += 30;
                $reasons[] = 'Stand kosong';
            }

            $overlaps = $this->findOverlaps($stand, $start, $end);
            if ($overlaps->isNotEmpty()) {
                $score -= 20;
                $warnings[] = 'Ada '.$overlaps->count().' reservasi yang tumpang tindih.';
            } else {
                $score += 10;
            }

            if ($preferredStandId && (int) $preferredStandId === $stand->id) {
                $score += 15;
                $reasons[] = 'Stand pilihan operator';
            }

            return [
                'stand_id' => $stand->id,
                'stand_code' => $stand->code,
                'stand_name' => $stand->name,
                'recommended' => $allowed && ! $occupied,
                'score' => $score,
                'reasons' => $reasons,
                'warnings' => $warnings,
                'allowed_aircraft' => $stand->aircraftTypes->pluck('code')->values(),
                'capacity_category' => $stand->capacity_category,
                'occupied' => $occupied,
            ];
        });

        return $scored->sortByDesc('score')->values()->all();
    }

    public function combineDateTime(?string $date, ?string $time): ?CarbonInterface
    {
        if (! $time || $time === '-') {
            return null;
        }

        $date = $date ?: CarbonImmutable::now()->toDateString();

        try {
            return CarbonImmutable::parse($date.' '.$time);
        } catch (\Throwable) {
            return null;
        }
    }
}
