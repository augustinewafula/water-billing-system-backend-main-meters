<?php

namespace App\Http\Requests;

use App\Enums\MeterMode;
use App\Enums\ValveStatus;
use App\Rules\uniqueMainMeterStation;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class CreateMainMeterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'numeric', 'unique:meters,number'],
            'mode' => ['required', new EnumValue(MeterMode::class, false)],
            'last_reading' => ['required', 'numeric'],
            'station_id' => ['required', 'string', 'exists:meter_stations,id', new uniqueMainMeterStation()],
            'valve_status' => ['required_if:mode,1', 'nullable', new EnumValue(ValveStatus::class, false)],
            'sim_card_number' => ['nullable', 'numeric'],
            'type_id' => ['required_if:mode,1', 'nullable', 'exists:meter_types,id'],
            'main_meter' => ['nullable', 'boolean']
        ];
    }

    public function messages(): array
    {
        return [
            'required_if' => 'The :attribute field is required when mode is automatic',
            'unique' => 'The :attribute already exists'
        ];
    }
}
