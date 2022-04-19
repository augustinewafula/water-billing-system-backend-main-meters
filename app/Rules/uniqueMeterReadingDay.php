<?php

namespace App\Rules;

use App\Models\DailyMeterReading;
use Illuminate\Contracts\Validation\Rule;

class uniqueMeterReadingDay implements Rule
{
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
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $date = now();
        $similar_readings = DailyMeterReading::where('meter_id', $value)
            ->whereDay('created_at', $date->day)
            ->whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->get()
            ->count();
        return $similar_readings <= 0;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return "Today's reading for this meter has been recorded";
    }
}
