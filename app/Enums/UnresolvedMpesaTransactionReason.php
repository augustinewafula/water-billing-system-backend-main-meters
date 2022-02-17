<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static InvalidAccountNumber()
 * @method static static MeterReadingNotFound()
 */
final class UnresolvedMpesaTransactionReason extends Enum
{
    public const InvalidAccountNumber = 0;
    public const MeterReadingNotFound = 1;
}
