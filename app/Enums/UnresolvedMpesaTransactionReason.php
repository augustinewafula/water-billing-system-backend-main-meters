<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static InvalidAccountNumber()
 * @method static static MeterReadingNotFound()
 */
final class UnresolvedMpesaTransactionReason extends Enum
{
    public const INVALID_ACCOUNT_NUMBER = 0;
}
