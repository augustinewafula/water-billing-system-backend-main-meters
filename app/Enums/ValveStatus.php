<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Closed()
 * @method static static Open()
 */
final class ValveStatus extends Enum
{
    const Closed =   0;
    const Open =   1;
}
