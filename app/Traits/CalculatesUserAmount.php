<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Models\ConnectionFee;
use App\Models\MeterCharge;
use App\Models\MeterReading;
use App\Models\MonthlyServiceCharge;
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

    public function calculateUserConnectionFeeDebt($user_id)
    {
        $unpaid_connection_fees = DB::table('connection_fees')
            ->where('user_id', $user_id)
            ->whereDate('month', '<=', now())
            ->whereStatus(PaymentStatus::NOT_PAID)
            ->sum('amount');
        $connection_fees_with_balance = ConnectionFee::where('user_id', $user_id)
            ->whereStatus(PaymentStatus::PARTIALLY_PAID)
            ->get();

        $balance_bills = 0;
        foreach ($connection_fees_with_balance as $connection_fee) {
            $balance = DB::table('connection_fee_payments')
                ->where('connection_fee_id', $connection_fee->id)
                ->latest()
                ->first()
                ->balance;
            $balance_bills += $balance;
        }

        return $unpaid_connection_fees + $balance_bills;
    }
}
