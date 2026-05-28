<?php

namespace App\Http\Requests\Api\V1\Provider;

use Illuminate\Foundation\Http\FormRequest;

class StoreProviderServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'uuid', 'exists:services,id'],
            'price_model' => ['required', 'in:fixed,hourly,quote'],
            'price_amount' => ['nullable', 'integer', 'min:0', 'required_unless:price_model,quote'],
            'custom_description' => ['nullable', 'string', 'max:2000'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
