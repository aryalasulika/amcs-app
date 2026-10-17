<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetStandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stands.reset') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'confirm' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan reset massal wajib diisi.',
            'reason.min' => 'Alasan reset minimal 5 karakter agar dapat diaudit.',
            'confirm.accepted' => 'Reset massal memerlukan konfirmasi berlapis.',
        ];
    }
}
