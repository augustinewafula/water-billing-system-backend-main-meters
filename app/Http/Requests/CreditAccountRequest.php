<?php

namespace App\Http\Requests;

use App\Rules\canGenerateToken;
use Illuminate\Foundation\Http\FormRequest;

class CreditAccountRequest extends FormRequest
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
     * @return array
     */
    public function rules()
    {
        return [
            'user_id' => ['required', 'string', 'exists:users,id', new canGenerateToken()],
            'amount' => ['required', 'numeric', 'min:1'],
            'mpesa_transaction_reference' => ['nullable', 'string'],
        ];
    }
}
