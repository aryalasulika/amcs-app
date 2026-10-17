<?php

namespace App\Http\Requests;

use App\Enums\AircraftCategory;
use App\Enums\FlightType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('movements.create') ?? false;
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
            'flight_number' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9\-]{2,32}$/'],
            'report_date' => ['nullable', 'date'],
            'airline_id' => ['nullable', 'integer', 'exists:airlines,id'],
            'airline_name' => ['nullable', 'string', 'max:255'],
            'registration' => ['nullable', 'string', 'max:32'],
            'aircraft_type_id' => ['nullable', 'integer', 'exists:aircraft_types,id'],
            'aircraft_type_name' => ['nullable', 'string', 'max:64'],
            'aircraft_category' => ['nullable', Rule::enum(AircraftCategory::class)],
            'flight_type' => ['nullable', Rule::enum(FlightType::class)],
            'origin' => ['nullable', 'string', 'size:4', 'regex:/^[A-Z]{4}$/'],
            'destination' => ['nullable', 'string', 'size:4', 'regex:/^[A-Z]{4}$/'],
            'parking_stand_id' => ['nullable', 'integer', 'exists:parking_stands,id'],
            'state' => ['nullable', Rule::in(['ENTERING', 'ON'])],
            'eta' => ['nullable', 'date_format:H:i'],
            'ata' => ['nullable', 'date_format:H:i'],
            'etd' => ['nullable', 'date_format:H:i'],
            'atd' => ['nullable', 'date_format:H:i'],
            'runway' => ['nullable', 'string', 'max:16'],
            'taxiway' => ['nullable', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:255', 'required_if:override,true'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'flight_number.regex' => 'Nomor penerbangan hanya boleh berisi huruf, angka, dan tanda hubung.',
            'origin.regex' => 'Kode origin harus berupa 4 huruf kode ICAO.',
            'destination.regex' => 'Kode destination harus berupa 4 huruf kode ICAO.',
            'override_reason.required_if' => 'Alasan override wajib diisi bila melakukan override aturan stand.',
        ];
    }
}
