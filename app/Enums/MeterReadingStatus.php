<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Closed()
 * @method static static Open()
 */
final class MeterReadingStatus extends Enum
{
    public const NotPaid = 0;
    public const Paid = 1;
    public const Balance = 2;
    public const OverPaid = 3;
}
