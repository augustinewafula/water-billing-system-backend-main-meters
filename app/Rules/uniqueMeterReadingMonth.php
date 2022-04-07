<?php

namespace App\Rules;

use App\Models\MeterReading;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Log;

class uniqueMeterReadingMonth implements Rule, DataAwareRule
{
    protected $data = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {

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
        $date = Carbon::createFromFormat('Y-m', $this->data['month']);
        $similar_readings = MeterReading::where('meter_id', $this->data['meter_id'])
            ->whereMonth('month', $date->month)
            ->whereYear('month', $date->year)
            ->get()
            ->count();
        Log::info($similar_readings);
        return $similar_readings <= 0;

    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        $meter_reading_month = Carbon::createFromFormat('Y-m', $this->data['month'])->isoFormat('MMMM YYYY');
        return "This customer's meter reading for $meter_reading_month already exists.";
    }

    public function setData($data): uniqueMeterReadingMonth
    {
        $this->data = $data;
        return $this;
    }
}
