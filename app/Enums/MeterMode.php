<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Manual()
 * @method static static Automatic()
 */
final class MeterMode extends Enum
{
    public const MANUAL = 0;
    public const AUTOMATIC = 1;
}
