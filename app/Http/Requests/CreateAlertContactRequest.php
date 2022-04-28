<?php

namespace App\Http\Requests;

use App\Enums\AlertContactTypes;
use BenSampo\Enum\Rules\EnumValue;
use Illuminate\Foundation\Http\FormRequest;

class CreateAlertContactRequest extends FormRequest
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
            'type' => ['required', new EnumValue(AlertContactTypes::class, false)],
            'value' => ['required', 'string', 'unique:alert_contacts']
        ];
    }

    public function messages(): array
    {
        return [
            'unique' => 'The alert contact already exists.'
        ];
    }
}
