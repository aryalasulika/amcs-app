<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AirlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('master-data.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
        }
        if ($this->has('flight_prefix')) {
            $this->merge(['flight_prefix' => mb_strtoupper(trim((string) $this->input('flight_prefix')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $airline = $this->route('airline');
        $airlineId = is_object($airline) ? $airline->id : $airline;

        return [
            'code' => ['required', 'string', 'max:16', Rule::unique('airlines', 'code')->ignore($airlineId)],
            'name' => ['required', 'string', 'max:255'],
            'flight_prefix' => ['nullable', 'string', 'max:16'],
            'default_aircraft_type_id' => ['nullable', 'integer', 'exists:aircraft_types,id'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
