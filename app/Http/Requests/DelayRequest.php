<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DelayRequest extends FormRequest
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
            'delay_minutes' => ['required', 'integer', 'min:1', 'max:2880'],
            'delay_reason' => ['required', 'string', 'max:64'],
            'delay_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
