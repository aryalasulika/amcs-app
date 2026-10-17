<?php

namespace App\Http\Resources;

use App\Models\AircraftType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AircraftType */
class AircraftTypeResource extends JsonResource
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
            'category' => $this->category?->value,
            'category_label' => $this->category?->label(),
            'wingspan' => $this->wingspan !== null ? (float) $this->wingspan : null,
            'max_weight' => $this->max_weight !== null ? (float) $this->max_weight : null,
            'active' => $this->active,
        ];
    }
}
