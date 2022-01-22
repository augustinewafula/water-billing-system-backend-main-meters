<?php

namespace App\Http\Requests;

use App\Enums\ValveStatus;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class CreateMeterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'numeric'],
            'valve_status' => ['required', new EnumValue(ValveStatus::class)],
            'station_id' => ['required', 'string', 'exists:meter_stations,id'],
            'type' => ['sometimes', 'required', 'exists:meter_types']
        ];
    }
}
