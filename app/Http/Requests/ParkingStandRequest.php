<?php

namespace App\Http\Requests;

use App\Enums\AircraftCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParkingStandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('master-data.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $stand = $this->route('parking_stand');
        $standId = is_object($stand) ? $stand->id : $stand;

        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('parking_stands', 'code')->ignore($standId)],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['nullable', 'boolean'],
            'capacity_category' => ['nullable', Rule::enum(AircraftCategory::class)],
            'gate_area' => ['nullable', 'string', 'max:255'],
            'position_x' => ['nullable', 'numeric'],
            'position_y' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'aircraft_type_ids' => ['nullable', 'array'],
            'aircraft_type_ids.*' => ['integer', 'exists:aircraft_types,id'],
        ];
    }
}
