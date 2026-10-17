<?php

namespace App\Http\Requests;

use App\Enums\AircraftCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecommendStandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('movements.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'aircraft_type_id' => ['nullable', 'integer', 'exists:aircraft_types,id'],
            'aircraft_type_name' => ['nullable', 'string', 'max:64'],
            'aircraft_category' => ['nullable', Rule::enum(AircraftCategory::class)],
            'report_date' => ['nullable', 'date'],
            'eta' => ['nullable', 'date_format:H:i'],
            'ata' => ['nullable', 'date_format:H:i'],
            'etd' => ['nullable', 'date_format:H:i'],
            'parking_stand_id' => ['nullable', 'integer', 'exists:parking_stands,id'],
        ];
    }
}
