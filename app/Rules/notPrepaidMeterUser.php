<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;
use Throwable;

class notPrepaidMeterUser implements Rule
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
        try {
            $meter_type = User::select('meter_types.name')
                ->join('meters', 'meters.id', 'users.meter_id')
                ->join('meter_types', 'meters.type_id', 'meter_types.id')
                ->where('users.account_number', $value)
                ->first()->name;
        } catch (Throwable $throwable) {
            $meter_type = 'not prepaid';
        }
        return $meter_type !== 'Prepaid';
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'Can not transfer from a prepaid meter user.';
    }
}
