<?php

namespace App\Traits;

trait ConvertsPhoneNumberToInternationalFormat
{
    public function phoneNumberToInternationalFormat(string $phoneNumber): string
    {
        $phoneNumber = substr($phoneNumber, 1);
        return '254'.$phoneNumber;
    }

}
