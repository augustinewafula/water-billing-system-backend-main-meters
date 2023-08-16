<?php

namespace App\Http\Requests;

use App\Rules\canGenerateToken;
use App\Rules\canPayConnectionFee;
use Illuminate\Foundation\Http\FormRequest;

class DebitAccountRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:1'],
        ];
    }
}
