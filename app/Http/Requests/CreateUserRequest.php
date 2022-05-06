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
            'first_monthly_service_fee_on' => ['required', 'date_format:Y-m'],
            'should_pay_connection_fee' => ['required', 'boolean']
        ];
    }
}
