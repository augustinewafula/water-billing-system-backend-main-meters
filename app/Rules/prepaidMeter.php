<?php

namespace App\Rules;

use App\Models\Meter;
use Illuminate\Contracts\Validation\Rule;
use Throwable;

class prepaidMeter implements Rule
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
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        try {
            $meter_type = Meter::select('meter_types.name')
                ->join('meter_types', 'meters.type_id', 'meter_types.id')
                ->where('meters.id', $value)
                ->first()->name;
        } catch (Throwable $throwable) {
            $meter_type = 'not prepaid';
        }
        return $meter_type === 'Prepaid';
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'Requires prepaid meters only';
    }
}
