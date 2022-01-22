<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Closed()
 * @method static static Open()
 */
final class ValveStatus extends Enum
{
    public const Closed = 0;
    public const Open = 1;
}
