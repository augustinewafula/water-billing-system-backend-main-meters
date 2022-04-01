<?php

namespace App\Http\Requests;

use App\Enums\MeterMode;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeterRequest extends FormRequest
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
            'station_id' => ['required', 'string', 'exists:meter_stations,id'],
            'type_id' => ['required_if:mode,1', 'nullable', 'exists:meter_types,id'],
            'mode' => ['required', new EnumValue(MeterMode::class, false)],
            'sim_card_number' => ['nullable', 'numeric'],
            'main_meter' => ['nullable', 'boolean']
        ];
    }

    public function messages(): array
    {
        return [
            'required_if' => 'The type field is required when mode is automatic',
            'unique' => 'The :attribute already exists'
        ];
    }
}
