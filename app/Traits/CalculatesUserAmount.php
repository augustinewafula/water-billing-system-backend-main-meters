<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Models\MeterCharge;
use App\Models\MeterReading;
use App\Models\ServiceCharge;
use DB;

trait CalculatesUserAmount
{
    public function calculateUserTotalAmount($user_account_balance, $transaction_amount, $monthly_service_charge_deducted, $connection_fee_deducted, $unaccounted_debt_deducted, $ignore_account_balance = false)
    {
        $user_total_amount = $transaction_amount;
        if ($monthly_service_charge_deducted > 0) {
            $user_total_amount -= $monthly_service_charge_deducted;
        }
        if ($unaccounted_debt_deducted > 0) {
            $user_total_amount -= $unaccounted_debt_deducted;
        }
        if ($connection_fee_deducted > 0) {
            $user_total_amount -= $connection_fee_deducted;
        }
        if ($user_account_balance > 0 && !$ignore_account_balance) {
            $user_total_amount += $user_account_balance;

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
