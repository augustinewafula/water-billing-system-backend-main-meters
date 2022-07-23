<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Closed()
 * @method static static Open()
 */
final class PaymentStatus extends Enum
{
    public const NOT_PAID = 0;
    public const PAID = 1;
    public const PARTIALLY_PAID = 2;
    public const OVER_PAID = 3;
}
