<?php

namespace App\Http\Resources;

use App\Models\MovementLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MovementLog */
class MovementLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_id' => $this->movement_id,
            'stand_code' => $this->stand_code,
            'flight_number' => $this->flight_number,
            'action' => $this->action,
            'payload' => $this->payload,
            'user' => $this->whenLoaded('user', fn () => $this->user?->only(['id', 'name'])),
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
