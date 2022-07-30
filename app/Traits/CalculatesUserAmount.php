<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Models\MeterCharge;
use App\Models\MeterReading;
use App\Models\ServiceCharge;
use App\Models\User;
use DB;

trait CalculatesUserAmount
{
    public function calculateUserTotalAmount($user_account_balance, $transaction_amount, $deductions)
    {
        $user_total_amount = $transaction_amount;
        if ($deductions->monthly_service_charge_deducted > 0) {
            $user_total_amount -= $deductions->monthly_service_charge_deducted;
        }
        if ($deductions->unaccounted_debt_deducted > 0) {
            $user_total_amount -= $deductions->unaccounted_debt_deducted;
        }
        if ($deductions->connection_fee_deducted > 0) {
            $user_total_amount -= $deductions->connection_fee_deducted;
        }

        $deductions_sum = $deductions->monthly_service_charge_deducted + $deductions->connection_fee_deducted + $deductions->unaccounted_debt_deducted;
        if ($user_account_balance > 0 && $deductions_sum === 0) {
            $user_total_amount += $user_account_balance;

        }

        return $user_total_amount;
    }

    public function calculateUserMeterReadingDebt($meter_id)
    {
        $user_unaccounted_debt = User::where('meter_id', $meter_id)->first()->unaccounted_debt;
        $unpaid_bills = DB::table('meter_readings')
            ->where('meter_id', $meter_id)
            ->whereDate('bill_due_at', '<=', now())
            ->whereStatus(PaymentStatus::NOT_PAID)
            ->sum('bill');
        $meter_readings_with_balance = MeterReading::where('meter_id', $meter_id)
            ->whereStatus(PaymentStatus::PARTIALLY_PAID)
            ->get();

        $balance_bills = 0;
        foreach ($meter_readings_with_balance as $reading) {
            $balance = DB::table('meter_billings')
                ->where('meter_reading_id', $reading->id)
                ->latest()
                ->first()
                ->balance;
            $balance_bills += $balance;
        }

        return $unpaid_bills + $balance_bills + $user_unaccounted_debt;
    }
}
