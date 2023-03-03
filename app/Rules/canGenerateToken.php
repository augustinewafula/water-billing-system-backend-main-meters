<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class canGenerateToken implements Rule
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
        $canGenerateToken = User::where('users.id', $value)
            ->join('meters', 'users.meter_id', 'meters.id')
            ->value('can_generate_token');

        return $canGenerateToken === null || $canGenerateToken;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return "Token generation for this user's meter has been disabled";
    }
}
