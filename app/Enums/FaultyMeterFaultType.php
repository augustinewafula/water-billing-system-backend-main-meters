<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class FaultyMeterFaultType extends Enum
{
    public const LostCommunication = 1;
    public const LowBattery = 2;
}
