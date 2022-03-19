<?php

namespace App\Traits;

use App\Models\Sms;
use Exception;

trait StoreSms
{

    /**
     * @throws Exception
     */
    public function storeSms($phone, $message, $status, $cost, $user_id): void
    {
        Sms::create([
            'phone' => $phone,
            'message' => $message,
            'status' => $status,
            'cost' => $cost,
            'user_id' => $user_id,
        ]);
    }

}
