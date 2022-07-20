<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

final class UnverifiedMpesaTransactionStatus extends Enum
{
    const PENDING =   0;
    const VERIFIED =   1;
    const UNVERIFIED = 2;
    const FRAUDLET = 2;
}
