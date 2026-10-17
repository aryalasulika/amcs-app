<?php

namespace App\Services;

use App\Enums\MovementState;
use App\Models\Movement;
use App\Models\ParkingStand;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the live apron board consumed by both the AMC dashboard and the
 * ATC tower monitor (FR-11).
 */
class StandBoardService
{
    /**
     * @return array{
     *     generated_at: string,
     *     version: string,
     *     counts: array{total: int, active: int, ron: int, delay: int, vacant: int},
     *     stands: array<int, array<string, mixed>>
     * }
     */
    public function board(?string $date = null): array
    {
        $date = $date ?: CarbonImmutable::now()->toDateString();

        $stands = ParkingStand::query()
            ->where('active', true)
            ->orderBy('id')
            ->get();

        /** @var Collection<int, Movement> $activeMovements */
        $activeMovements = Movement::query()
            ->whereIn('state', MovementState::activeValues())
            ->with(['parkingStand', 'airline', 'aircraftType'])
            ->get()
            ->keyBy('parking_stand_id');

        $standItems = [];
        $counts = ['total' => $stands->count(), 'active' => 0, 'ron' => 0, 'delay' => 0, 'vacant' => 0];

        foreach ($stands as $stand) {
            $movement = $activeMovements->get($stand->id);
            $state = $movement?->state ?? MovementState::VACANT;

            if ($movement) {
                $counts['active']++;
                if ($state === MovementState::RON) {
                    $counts['ron']++;
                }
                if ($state === MovementState::DELAY) {
                    $counts['delay']++;
                }
            } else {
                $counts['vacant']++;
            }

            $standItems[] = [
                'id' => $stand->id,
                'code' => $stand->code,
                'name' => $stand->name,
                'capacity_category' => $stand->capacity_category,
                'gate_area' => $stand->gate_area,
                'position' => [
                    'x' => $stand->position_x !== null ? (float) $stand->position_x : null,
                    'y' => $stand->position_y !== null ? (float) $stand->position_y : null,
                ],
                'state' => $state->value,
                'state_label' => $state->label(),
                'atc_label' => $state->atcLabel(),
                'allowed_aircraft' => $stand->aircraftTypes()->pluck('code')->values()->all(),
                'movement' => $movement ? $this->movementPayload($movement) : null,
            ];
        }

        $version = $activeMovements->max('updated_at')?->timestamp;
        $version ??= $stands->max('updated_at')?->timestamp;

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'version' => (string) ($version ?? 0),
            'date' => $date,
            'counts' => $counts,
            'stands' => $standItems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function movementPayload(Movement $movement): array
    {
        return [
            'id' => $movement->id,
            'flight_number' => $movement->flight_number,
            'airline' => $movement->airline_name,
            'registration' => $movement->registration,
            'aircraft_type' => $movement->aircraft_type_name,
            'flight_type' => $movement->flight_type,
            'origin' => $movement->origin,
            'destination' => $movement->destination,
            'route' => $movement->route(),
            'state' => $movement->state instanceof MovementState ? $movement->state->value : $movement->state,
            'state_label' => $movement->state instanceof MovementState ? $movement->state->label() : $movement->state,
            'eta' => $movement->eta,
            'ata' => $movement->ata,
            'etd' => $movement->etd,
            'atd' => $movement->atd,
            'runway' => $movement->runway,
            'taxiway' => $movement->taxiway,
            'delay_minutes' => $movement->delay_minutes,
            'delay_reason' => $movement->delay_reason,
            'report_date' => $movement->report_date?->toDateString(),
            'updated_at' => $movement->updated_at?->toIso8601String(),
        ];
    }
}
