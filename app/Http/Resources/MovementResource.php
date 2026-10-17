<?php

namespace App\Http\Resources;

use App\Enums\MovementState;
use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Movement */
class MovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->state instanceof MovementState ? $this->state : MovementState::tryFrom((string) $this->state);

        return [
            'id' => $this->id,
            'flight_number' => $this->flight_number,
            'airline' => [
                'id' => $this->airline_id,
                'name' => $this->airline_name,
            ],
            'registration' => $this->registration,
            'aircraft_type' => [
                'id' => $this->aircraft_type_id,
                'code' => $this->aircraft_type_name,
                'category' => $this->whenLoaded('aircraftType', fn () => $this->aircraftType?->category?->value),
            ],
            'flight_type' => $this->flight_type,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'route' => $this->route(),
            'parking_stand' => $this->whenLoaded('parkingStand', fn () => [
                'id' => $this->parkingStand?->id,
                'code' => $this->parkingStand?->code,
                'name' => $this->parkingStand?->name,
            ]),
            'parking_stand_id' => $this->parking_stand_id,
            'state' => $state?->value,
            'state_label' => $state?->label(),
            'atc_label' => $state?->atcLabel(),
            'is_active' => $this->isActive(),
            'eta' => $this->eta,
            'ata' => $this->ata,
            'etd' => $this->etd,
            'atd' => $this->atd,
            'runway' => $this->runway,
            'taxiway' => $this->taxiway,
            'delay_minutes' => $this->delay_minutes,
            'delay_reason' => $this->delay_reason,
            'delay_notes' => $this->delay_notes,
            'notes' => $this->notes,
            'override_reason' => $this->override_reason,
            'report_date' => $this->report_date?->toDateString(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->only(['id', 'name'])),
            'updated_by' => $this->whenLoaded('updater', fn () => $this->updater?->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'logs' => MovementLogResource::collection($this->whenLoaded('logs')),
        ];
    }
}
