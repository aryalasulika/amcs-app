<?php

namespace App\Http\Requests;

use App\Enums\AircraftCategory;
use App\Enums\FlightType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('movements.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn ($value) => is_string($value) ? mb_strtoupper(trim($value)) : $value,
            array_intersect_key($this->all(), array_flip([
                'flight_number', 'origin', 'destination', 'registration',
            ]))
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'flight_number' => ['sometimes', 'string', 'max:32', 'regex:/^[A-Z0-9\-]{2,32}$/'],
            'report_date' => ['sometimes', 'date'],
            'airline_id' => ['sometimes', 'nullable', 'integer', 'exists:airlines,id'],
            'airline_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'registration' => ['sometimes', 'nullable', 'string', 'max:32'],
            'aircraft_type_id' => ['sometimes', 'nullable', 'integer', 'exists:aircraft_types,id'],
            'aircraft_type_name' => ['sometimes', 'nullable', 'string', 'max:64'],
            'aircraft_category' => ['sometimes', 'nullable', Rule::enum(AircraftCategory::class)],
            'flight_type' => ['sometimes', Rule::enum(FlightType::class)],
            'origin' => ['sometimes', 'nullable', 'string', 'size:4', 'regex:/^[A-Z]{4}$/'],
            'destination' => ['sometimes', 'nullable', 'string', 'size:4', 'regex:/^[A-Z]{4}$/'],
            'parking_stand_id' => ['sometimes', 'nullable', 'integer', 'exists:parking_stands,id'],
            'eta' => ['sometimes', 'nullable', 'date_format:H:i'],
            'ata' => ['sometimes', 'nullable', 'date_format:H:i'],
            'etd' => ['sometimes', 'nullable', 'date_format:H:i'],
            'atd' => ['sometimes', 'nullable', 'date_format:H:i'],
            'runway' => ['sometimes', 'nullable', 'string', 'max:16'],
            'taxiway' => ['sometimes', 'nullable', 'string', 'max:16'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['sometimes', 'nullable', 'string', 'max:255', 'required_if:override,true'],
        ];
    }
}
