<?php

namespace App\Http\Requests\ExternalRequests;

use App\Enums\MeterType;
use Illuminate\Validation\Rule;

class StoreMeterTokenRequest extends BaseFormRequest
{

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'meter_number' => ['required', 'string', 'max:20'],
            'units' => ['required', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'meter_type' => [
                'required',
                'string',
                Rule::in(MeterType::values()),
            ],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'meter_number.required' => 'The meter number is required.',
            'units.required' => 'The number of units is required.',
            'units.numeric' => 'Units must be a number.',
            'units.gt' => 'Units must be greater than 0.',
            'amount.required' => 'The amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.gt' => 'Amount must be greater than 0.',
            'meter_type.required' => 'Meter type is required.',
            'meter_type.in' => 'Meter type must be either water or energy.',
        ];
    }
}
