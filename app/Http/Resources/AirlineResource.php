<?php

namespace App\Http\Resources;

use App\Models\Airline;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Airline */
class AirlineResource extends JsonResource
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
            'flight_prefix' => $this->flight_prefix,
            'default_aircraft_type_id' => $this->default_aircraft_type_id,
            'default_aircraft_type' => $this->whenLoaded('defaultAircraftType', fn () => $this->defaultAircraftType ? [
                'id' => $this->defaultAircraftType->id,
                'code' => $this->defaultAircraftType->code,
                'name' => $this->defaultAircraftType->name,
            ] : null),
            'active' => $this->active,
        ];
    }
}
