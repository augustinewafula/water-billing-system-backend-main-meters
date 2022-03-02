<?php

namespace App\Http\Requests;

use App\Rules\notPrepaidMeter;
use Illuminate\Foundation\Http\FormRequest;

class CreateDailyMeterReadingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'meter_id' => ['required', 'string', 'exists:meters,id', new notPrepaidMeter()],
            'reading' => ['required', 'numeric'],
        ];
    }
}
