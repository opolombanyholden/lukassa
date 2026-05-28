<?php

namespace App\Http\Requests\Api\V1\Provider;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProviderServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'price_model' => ['nullable', 'in:fixed,hourly,quote'],
            'price_amount' => ['nullable', 'integer', 'min:0'],
            'custom_description' => ['nullable', 'string', 'max:2000'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
