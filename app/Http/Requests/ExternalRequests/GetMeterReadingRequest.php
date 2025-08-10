<?php

namespace App\Http\Requests\ExternalRequests;

use App\Enums\MeterType;
use Illuminate\Validation\Rule;

class GetMeterReadingRequest extends BaseFormRequest
{

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'meter_number' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'meter_number.required' => 'The meter number is required.',
        ];
    }
}
