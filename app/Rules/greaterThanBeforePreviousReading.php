<?php

namespace App\Rules;

use App\Models\MeterReading;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Log;

class greaterThanBeforePreviousReading implements Rule, DataAwareRule
{
    protected $data = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        Log::info(json_encode($this->data));
        $previous_reading = MeterReading::where('meter_id', $this->data['meter_id'])
            ->latest()
            ->limit(1)
            ->first()
            ->previous_reading;
        return $value >= $previous_reading;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Current reading can not be less than the previous one';
    }

    public function setData($data): greaterThanBeforePreviousReading
    {
        $this->data = $data;
        return $this;
    }
}
