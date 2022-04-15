<?php

namespace App\Traits;

use App\Models\MeterCharge;
use App\Models\ServiceCharge;

trait CalculatesUserTotalAmount
{
    public function calculateUserTotalAmount($user_account_balance, $transaction_amount, $monthly_service_charge_deducted)
    {
        $user_total_amount = $transaction_amount;
        $service_charge_overpaid = 0;
        if ($monthly_service_charge_deducted > 0) {
            $user_total_amount = $transaction_amount - $monthly_service_charge_deducted;
            $service_charge_overpaid = $user_total_amount;
        }
        if ($user_account_balance > 0) {
            $user_total_amount += $user_account_balance;

        }
        if($service_charge_overpaid > 0){
            $user_total_amount -= $service_charge_overpaid;
        }

        return $user_total_amount;
    }
}
