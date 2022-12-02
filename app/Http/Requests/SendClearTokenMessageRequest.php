<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendClearTokenMessageRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meter_id' => ['required', 'string'],
            'recipient' => ['required', 'string'],
            'phone_number' => ['required_if:recipient,specified', 'nullable', 'numeric'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }
}
