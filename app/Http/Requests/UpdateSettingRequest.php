<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
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
            'prepaid_cost_per_unit' => ['required', 'numeric'],
            'prepaid_service_charge_in' => ['required', 'boolean'],
            'prepaid_service_charge' => ['required', 'string'],
            'postpaid_cost_per_unit' => ['required', 'numeric'],
            'postpaid_service_charge_in' => ['required', 'boolean'],
            'postpaid_service_charge' => ['required', 'string'],
            'bill_due_on' => ['required', 'numeric'],
            'delay_meter_reading_sms' => ['required', 'boolean'],
            'meter_reading_sms_delay_days' => ['required', 'numeric'],
        ];
    }
}
