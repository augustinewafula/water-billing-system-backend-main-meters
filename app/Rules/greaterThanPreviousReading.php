<?php

namespace App\Rules;

use App\Models\Meter;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class greaterThanPreviousReading implements Rule, DataAwareRule
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
    public function passes($attribute, $value): bool
    {
        $previous_reading = Meter::find($this->data['meter_id'])->last_reading;
        return $value >= $previous_reading;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'Current reading can not be less than the previous one';
    }

    public function setData($data): greaterThanPreviousReading
    {
        $this->data = $data;
        return $this;
    }
}
