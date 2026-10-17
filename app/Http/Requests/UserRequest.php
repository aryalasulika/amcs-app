<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');
        $userId = is_object($user) ? $user->id : $user;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [
                $isUpdate ? 'sometimes' : 'required',
                'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'password' => [$isUpdate ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'active' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }
}
