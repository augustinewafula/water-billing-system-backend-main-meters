<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static Manual()
 * @method static static Automatic()
 */
final class MeterMode extends Enum
{
    public const Manual = 0;
    public const Automatic = 1;
}
