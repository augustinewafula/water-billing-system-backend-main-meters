<?php

namespace App\Traits;


trait GetsUserConnectionFeeBalance
{
    public function getUserConnectionFeeBalance($user)
    {
        return $user->connection_fee - $user->total_connection_fee_paid;

    }

}
