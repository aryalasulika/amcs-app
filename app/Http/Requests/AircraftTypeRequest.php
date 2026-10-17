<?php

namespace App\Http\Requests;

use App\Enums\AircraftCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AircraftTypeRequest extends FormRequest
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
        $type = $this->route('aircraft_type');
        $typeId = is_object($type) ? $type->id : $type;

        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('aircraft_types', 'code')->ignore($typeId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(AircraftCategory::class)],
            'wingspan' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'max_weight' => ['nullable', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
