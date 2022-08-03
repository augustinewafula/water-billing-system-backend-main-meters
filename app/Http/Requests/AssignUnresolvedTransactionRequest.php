<?php

namespace App\Http\Requests;

use App\Rules\canPayConnectionFee;
use Illuminate\Foundation\Http\FormRequest;

class AssignUnresolvedTransactionRequest extends FormRequest
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
            'id' => ['required', 'string', 'exists:unresolved_mpesa_transactions'],
            'user_id' => ['required', 'string', 'exists:users,id', new canPayConnectionFee()],
            'account_type' => ['required', 'numeric', 'in:1,2'],
        ];
    }
}
