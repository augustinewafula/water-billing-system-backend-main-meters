<?php

namespace App\Http\Requests;

use App\Rules\uniqueMeterReadingMonth;
use App\Rules\uniqueUnreadMeterMonth;
use Illuminate\Foundation\Http\FormRequest;

class CreateUnreadMeterRequest extends FormRequest
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
            'meter_id' => ['required', 'string', 'exists:meters,id'],
            'month' => ['required', 'date_format:Y-m', new uniqueUnreadMeterMonth(), new uniqueMeterReadingMonth()],
            'reason' => ['required', 'string'],
        ];
    }
}
