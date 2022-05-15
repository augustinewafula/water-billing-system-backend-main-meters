<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'unique:users', 'max:50'],
            'phone' => ['required', 'numeric', 'digits:10'],
            'account_number' => ['required', 'string', 'unique:users', 'max:50'],
            'meter_id' => ['required', 'string', 'exists:meters,id', 'unique:users,meter_id', 'max:50'],
            'should_pay_connection_fee' => ['required', 'boolean'],
            'first_connection_fee_on' => ['required_if:should_pay_connection_fee,true', 'nullable', 'date_format:Y-m'],
            'use_custom_charges_for_cost_per_unit' => ['required', 'boolean'],
            'cost_per_unit' => ['required_if:use_custom_charges_for_cost_per_unit,true', 'nullable', 'numeric'],
            'communication_channels' => ['required', 'string'],
        ];
    }
}
