<?php

namespace App\Http\Requests;

use App\Rules\accountNumberCanGenerateToken;
use App\Rules\accountNumberCanPayConnectionFee;
use App\Rules\notPrepaidMeterUser;
use Illuminate\Foundation\Http\FormRequest;

class TransferTransactionRequest extends FormRequest
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
            'transaction_id' => ['required', 'string', 'exists:mpesa_transactions,id', 'max:255'],
            'to_account_number' => ['required', 'string', 'different:from_account_number', 'exists:users,account_number', 'max:50', new accountNumberCanGenerateToken(), new accountNumberCanPayConnectionFee()],
            'from_account_number' => ['required', 'string', 'exists:users,account_number', 'max:50', new notPrepaidMeterUser()],
        ];
    }

    public function messages(): array
    {
        return [
            'to_account_number.different' => 'You cannot transfer to the same account as you are transferring from.',
        ];
    }
}
