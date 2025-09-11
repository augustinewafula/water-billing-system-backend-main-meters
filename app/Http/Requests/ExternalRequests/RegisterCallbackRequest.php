<?php

namespace App\Http\Requests\ExternalRequests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCallbackRequest extends FormRequest
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
            'callback_url' => ['required', 'url', 'regex:/^https:\/\//', 'max:255'],
            'secret_token' => ['nullable', 'string', 'min:32', 'max:128'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if ($user && \App\Models\ClientCallbackUrl::where('client_id', $user->id)->exists()) {
                $validator->errors()->add('callback_url', 'You already have a registered callback URL. Please use the update endpoint to modify it.');
            }
        });
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'callback_url.required' => 'Callback URL is required.',
            'callback_url.url' => 'Callback URL must be a valid URL.',
            'callback_url.regex' => 'Callback URL must use HTTPS protocol.',
            'secret_token.min' => 'Secret token must be at least 32 characters long.',
        ];
    }
}
