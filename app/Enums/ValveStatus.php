<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Closed()
 * @method static static Open()
 */
final class ValveStatus extends Enum
{
    public const CLOSED = 0;
    public const OPEN = 1;
}
