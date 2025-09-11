<?php

namespace App\Http\Requests\ExternalRequests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCallbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'callback_url' => ['sometimes', 'url', 'regex:/^https:\/\//', 'max:255'],
            'secret_token' => ['nullable', 'string', 'min:32', 'max:128'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'callback_url.url' => 'Callback URL must be a valid URL.',
            'callback_url.regex' => 'Callback URL must use HTTPS protocol.',
            'secret_token.min' => 'Secret token must be at least 32 characters long.',
        ];
    }
}
