<?php

namespace App\Http\Resources;

use App\Models\ParkingStand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ParkingStand */
class ParkingStandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'active' => $this->active,
            'capacity_category' => $this->capacity_category,
            'gate_area' => $this->gate_area,
            'position' => [
                'x' => $this->position_x !== null ? (float) $this->position_x : null,
                'y' => $this->position_y !== null ? (float) $this->position_y : null,
            ],
            'notes' => $this->notes,
            'occupied' => $this->when(! $this->relationLoaded('movements'), fn () => $this->isOccupied()),
            'aircraft_types' => AircraftTypeResource::collection($this->whenLoaded('aircraftTypes')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
