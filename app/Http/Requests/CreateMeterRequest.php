<?php

namespace App\Http\Requests;

use App\Enums\MeterCategory;
use App\Enums\MeterMode;
use App\Enums\PrepaidMeterType;
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
            'number' => ['required', 'string', 'unique:meters,number'],
            'mode' => ['required', new EnumValue(MeterMode::class, false)],
            'last_reading' => ['required', 'numeric'],
            'station_id' => ['required', 'string', 'exists:meter_stations,id'],
            'valve_status' => ['required_if:mode,1', 'nullable', new EnumValue(ValveStatus::class, false)],
            'sim_card_number' => ['nullable', 'numeric'],
            'type_id' => ['required_if:mode,1', 'nullable', 'exists:meter_types,id'],
            'main_meter' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string'],
            'has_location_coordinates' => ['required', 'boolean'],
            'location_coordinates.lat' => ['required_if:has_location_coordinates,1', 'nullable', 'between:-90,90'],
            'location_coordinates.lng' => ['required_if:has_location_coordinates,1', 'nullable', 'between:-180,180'],
            'category' => ['required', new EnumValue(MeterCategory::class, false)],
            'prepaid_meter_type' => ['required_if:is_prepaid_meter,1', new EnumValue(PrepaidMeterType::class, false)],
            'concentrator_id' => ['nullable', 'exists:concentrators,id'],
            'use_prism_vend' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required_if' => 'The :attribute field is required when mode is automatic',
            'unique' => 'The :attribute already exists',
            'location_coordinates.lat.between' => 'The latitude must be between -90 and 90',
            'location_coordinates.lng.between' => 'The longitude must be between -180 and 180',
        ];
    }
}
