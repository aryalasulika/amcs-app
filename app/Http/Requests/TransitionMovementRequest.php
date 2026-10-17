<?php

namespace App\Http\Requests;

use App\Enums\MovementState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('movements.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'state' => ['required', Rule::enum(MovementState::class)],
            'ata' => ['nullable', 'date_format:H:i'],
            'atd' => ['nullable', 'date_format:H:i'],
            'delay_minutes' => ['nullable', 'integer', 'min:1', 'max:2880'],
            'delay_reason' => ['nullable', 'string', 'max:64'],
            'delay_notes' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:255', 'required_if:override,true'],
        ];
    }
}
