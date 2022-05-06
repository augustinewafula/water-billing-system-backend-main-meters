<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SmsCallbackRequest extends FormRequest
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
            'id' => ['required', 'string', 'exists:sms,message_id'],
            'status' => ['required', 'string'],
            'phoneNumber' => ['required', 'string'],
            'networkCode' => ['required', 'string'],
            'failureReason' => ['nullable', 'string'],
        ];
    }
}
