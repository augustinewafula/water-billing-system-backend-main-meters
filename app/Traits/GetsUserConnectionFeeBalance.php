<?php

namespace App\Traits;

use App\Models\ConnectionFeeCharge;

trait GetsUserConnectionFeeBalance
{
    public function getUserConnectionFeeBalance($meter_station_id, $total_connection_fee_paid)
    {
        $connection_fee_charges = ConnectionFeeCharge::where('station_id', $meter_station_id)
            ->first();
        $connection_fee = $connection_fee_charges->connection_fee;
        return $connection_fee - $total_connection_fee_paid;

    }

}
