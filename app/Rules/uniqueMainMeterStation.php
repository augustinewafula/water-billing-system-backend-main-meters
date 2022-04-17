<?php

namespace App\Rules;

use App\Models\Meter;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class uniqueMainMeterStation implements Rule, DataAwareRule
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
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $similar_station_main_meter = Meter::where('main_meter', true)
            ->where('station_id', $value)
            ->get()
            ->count();
        return $similar_station_main_meter <= 0;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'Main meter for this station already exists.';
    }

    public function setData($data): uniqueMainMeterStation
    {
        $this->data = $data;
        return $this;
    }
}
