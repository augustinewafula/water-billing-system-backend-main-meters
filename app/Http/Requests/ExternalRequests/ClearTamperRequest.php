<?php

namespace App\Http\Requests\ExternalRequests;

class ClearTamperRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'meter_number' => [
                'required',
                'string',
                'max:20',
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'meter_number.required' => 'Meter number is required.',
            'meter_number.max' => 'Meter number must not exceed 20 characters.',
        ];
    }
}
