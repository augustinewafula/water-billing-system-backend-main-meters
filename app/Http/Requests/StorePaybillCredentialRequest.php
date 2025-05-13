<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaybillCredentialRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shortcode' => 'required|string|unique:paybill_credentials,shortcode',
            'consumer_key' => 'required|string',
            'consumer_secret' => 'required|string',
            'initiator_username' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
        ];
    }
}
