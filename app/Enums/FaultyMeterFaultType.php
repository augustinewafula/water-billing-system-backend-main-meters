<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class FaultyMeterFaultType extends Enum
{
    public const LOST_COMMUNICATION = 1;
    public const LOW_BATTERY = 2;
}
