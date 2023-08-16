<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class canPayConnectionFee implements Rule, DataAwareRule
{

    /**
     * Determine whether the user should pass based on the account type or pay a connection fee.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool Returns true if account_type is 1 or if the user should pay the connection fee.
     */
    public function passes($attribute, $value): bool
    {
        // Check if 'account_type' key exists in the data array
        if (array_key_exists('account_type', $this->data) && $this->data['account_type'] === 1) {
            return true;
        }

        // Check if 'user_id' key exists in the data array before attempting to find the user
        if (array_key_exists('user_id', $this->data)) {
            return User::findOrFail($this->data['user_id'])->should_pay_connection_fee;
        }

        // Return false if 'user_id' key is not found
        return false;
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

    public function setData($data): canPayConnectionFee|static
    {
        $this->data = $data;
        return $this;
    }
}
