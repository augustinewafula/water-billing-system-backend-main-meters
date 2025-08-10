<?php

namespace App\Http\Requests\ExternalRequests;

use Illuminate\Foundation\Http\FormRequest;
use BenSampo\Enum\Rules\EnumValue;
use App\Enums\ValveStatus;

class UpdateValveStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'valve_status' => ['required', new EnumValue(ValveStatus::class, false)],
        ];
    }

    public function messages(): array
    {
        return [
            'valve_status.required' => 'The valve status is required.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

