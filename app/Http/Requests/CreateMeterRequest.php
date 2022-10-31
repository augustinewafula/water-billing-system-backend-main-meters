<?php

namespace App\Http\Requests;

use App\Enums\MeterMode;
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
            'number' => ['required', 'numeric', 'unique:meters,number'],
            'mode' => ['required', new EnumValue(MeterMode::class, false)],
            'last_reading' => ['required', 'numeric'],
            'station_id' => ['required', 'string', 'exists:meter_stations,id'],
            'valve_status' => ['required_if:mode,1', 'nullable', new EnumValue(ValveStatus::class, false)],
            'sim_card_number' => ['nullable', 'numeric'],
            'type_id' => ['required_if:mode,1', 'nullable', 'exists:meter_types,id'],
            'main_meter' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string'],
            'has_location' => ['required', 'boolean'],
            'location.lat' => ['required_if:has_location,1', 'nullable', 'between:-90,90'],
            'location.lng' => ['required_if:has_location,1', 'nullable', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'required_if' => 'The :attribute field is required when mode is automatic',
            'unique' => 'The :attribute already exists',
            'location.lat.between' => 'The latitude must be between -90 and 90',
            'location.lng.between' => 'The longitude must be between -180 and 180',
        ];
    }
}
