<?php

namespace App\Traits;

use App\Models\Sms;
use Exception;

trait StoresSms
{

    /**
     * @throws Exception
     */
    public function storeSms($phone, $message, $message_id, $status, $cost, $user_id, $station_id): void
    {
        Sms::create([
            'phone' => $phone,
            'message' => $message,
            'message_id' => $message_id,
            'status' => $status,
            'cost' => $cost,
            'user_id' => $user_id,
            'station_id' => $station_id
        ]);
    }

}
