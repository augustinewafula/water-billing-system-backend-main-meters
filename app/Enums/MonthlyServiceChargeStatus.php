<?php

namespace App\Enums;

use BenSampo\Enum\Enum;


final class MonthlyServiceChargeStatus extends Enum
{
    public const Paid = 1;
    public const Balance = 2;
    public const OverPaid = 3;
}
