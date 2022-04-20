<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeterStationRequest extends FormRequest
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
            'name' => ['required', 'string', "unique:meter_stations,name,$this->id", 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'paybill_number' => ['required', 'numeric', 'digits:6'],
        ];
    }
}
