<?php

namespace App\Http\Requests;

use App\Rules\mpesaTransactionNotConsumed;
use App\Rules\prepaidMeter;
use Illuminate\Foundation\Http\FormRequest;

class CreateMeterTokenRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'meter_id' => ['required', 'string', 'exists:meters,id', new prepaidMeter()],
            'mpesa_transaction_reference' => ['bail', 'required', 'string', 'exists:mpesa_transactions,TransID', new mpesaTransactionNotConsumed()],
        ];
    }
}
