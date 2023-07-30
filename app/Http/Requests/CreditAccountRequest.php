<?php

namespace App\Http\Requests;

use App\Rules\canGenerateToken;
use App\Rules\canPayConnectionFee;
use Illuminate\Foundation\Http\FormRequest;

class CreditAccountRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;
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
    public function rules()
    {
        return [
            'user_id' => ['required', 'string', 'exists:users,id', new canGenerateToken(), new canPayConnectionFee()],
            'amount' => ['required', 'numeric', 'min:1'],
            'account_type' => ['required', 'numeric', 'in:1,2'],
            'mpesa_transaction_reference' => ['nullable', 'string'],
            'reason_for_crediting' => ['nullable', 'string'],
        ];
    }
}
