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
            'prepay_cost_per_unit' => ['required', 'numeric'],
            'prepay_service_charge_in' => ['required', 'boolean'],
            'prepay_service_charge' => ['required', 'numeric'],
            'postpaid_cost_per_unit' => ['required', 'numeric'],
            'postpaid_service_charge_in' => ['required', 'boolean'],
            'postpaid_service_charge' => ['required', 'numeric'],
            'bill_due_days' => ['required', 'numeric'],
            'meter_reading_sms_delay_days' => ['required', 'numeric'],
        ];
    }
}
