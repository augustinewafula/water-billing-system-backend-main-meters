<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class UnverifiedMpesaTransactionStatus extends Enum
{
    public const PENDING =   0;
    public const VERIFIED =   1;
    public const UNVERIFIED = 2;
    public const FRAUDULENT = 2;
}
