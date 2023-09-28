<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConcentratorRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'concentrator_id' => 'required|string|unique:concentrators,concentrator_id,' . $this->concentrator->id,
            'name' => 'required|string',
        ];
    }
}
