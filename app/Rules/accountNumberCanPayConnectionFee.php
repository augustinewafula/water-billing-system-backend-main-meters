<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class accountNumberCanPayConnectionFee implements Rule, DataAwareRule
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
        if ($this->data['account_type'] === 1) {
            return true;
        }
        return User::where('account_number', $value)->first()->should_pay_connection_fee;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return 'This user is not configured to be able to pay connection fee.';
    }

    public function setData($data): accountNumberCanPayConnectionFee|static
    {
        $this->data = $data;
        return $this;
    }

}
