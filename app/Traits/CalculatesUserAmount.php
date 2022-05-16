<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Models\MeterCharge;
use App\Models\MeterReading;
use App\Models\ServiceCharge;
use DB;

trait CalculatesUserAmount
{
    public function calculateUserTotalAmount($user_account_balance, $transaction_amount, $monthly_service_charge_deducted, $connection_fee_deducted)
    {
        $user_total_amount = $transaction_amount;
        $service_charge_overpaid = 0;
        $connection_fee_overpaid = 0;
        if ($monthly_service_charge_deducted > 0) {
            $user_total_amount = $transaction_amount - $monthly_service_charge_deducted;
            $service_charge_overpaid = $user_total_amount;
        }
        if ($connection_fee_deducted > 0) {
            $user_total_amount = $transaction_amount - $connection_fee_deducted;
            $connection_fee_overpaid = $user_total_amount;
        }
        if ($user_account_balance > 0) {
            $user_total_amount += $user_account_balance;

        }
        if($service_charge_overpaid > 0){
            $user_total_amount -= $service_charge_overpaid;
        }
        if($connection_fee_overpaid > 0){
            $user_total_amount -= $connection_fee_overpaid;
        }

        return $user_total_amount;
    }

    public function calculateUserMeterReadingDebt($meter_id)
    {
        $unpaid_bills = DB::table('meter_readings')
            ->where('meter_id', $meter_id)
            ->whereDate('bill_due_at', '<=', now())
            ->whereStatus(PaymentStatus::NotPaid)
            ->sum('bill');
        $meter_reading = MeterReading::where('meter_id', $meter_id)->first();
        $balance_bills = DB::table('meter_billings')
            ->where('meter_reading_id', $meter_reading->id)
            ->sum('balance');

        return $unpaid_bills + $balance_bills;
    }
}
