<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'type' => ['required', 'string', 'in:client,prestataire'],
            'email' => ['nullable', 'email', 'unique:users,email'],
        ];
    }
}
