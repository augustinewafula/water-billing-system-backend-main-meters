<?php

namespace App\Http\Requests\ExternalRequests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class BaseFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'data' => null,
            'errors' => [
                'type' => 'ValidationException',
                'details' => $validator->errors(),
            ],
        ], 422);

        throw new HttpResponseException($response);
    }
}
