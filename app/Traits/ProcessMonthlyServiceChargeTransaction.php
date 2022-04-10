<?php

namespace App\Traits;

use App\Enums\MonthlyServiceChargeStatus;
use App\Models\MonthlyServiceCharge;
use App\Models\MonthlyServiceChargeReport;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Log;
use Str;
use Throwable;

trait ProcessMonthlyServiceChargeTransaction
{
    public function hasMonthlyServiceChargeDebt($user): bool
    {
        $last_monthly_service_charge = MonthlyServiceCharge::where('user_id', $user->user_id)
            ->latest('month')
            ->limit(1)
            ->first();
        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();

        if ($last_monthly_service_charge) {
            $last_monthly_service_charge_month = Carbon::createFromFormat('Y-m', $last_monthly_service_charge->month);
            if ($last_monthly_service_charge_month->lessThan($firstDayOfCurrentMonth)) {
                return true;
            }
            return false;

        }

        $user_first_monthly_service_fee_on = Carbon::createFromFormat('Y-m-d H:i:s', $user->first_monthly_service_fee_on);
        if ($user_first_monthly_service_fee_on->lessThan($firstDayOfCurrentMonth)) {
            return true;
        }
        return false;

    }

    public function userHasFundsInAccount($user): bool
    {
        return $user->account_balance > 0;
    }

    public function userHasPaidFully($user_total_amount, $monthly_service_charge): bool
    {
        return $user_total_amount >= $monthly_service_charge;
    }

    public function storeMonthlyServiceCharge($user_id, $mpesa_transaction_id, $transaction_amount)
    {
        $user = User::findOrFail($user_id);
        $user_total_amount = $transaction_amount;
        $amount_paid = $transaction_amount;
        $monthly_service_charge = Setting::where('key', 'monthly_service_charge')
            ->first()
            ->value;
        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();
        $month_to_bill = $this->getFirstMonthToBill($user);
        $total_monthly_service_charge_paid = 0;

        while ($month_to_bill->lessThanOrEqualTo($firstDayOfCurrentMonth)) {
            $credit = 0;
            $user = $user->refresh();
            if ($this->userHasFundsInAccount($user)) {
                $user_total_amount += $user->account_balance;
                $credit = $user->account_balance;
            }
            if ($user_total_amount < 0 && $amount_paid === 0) {
                break;
            }
            $last_monthly_service_charge = MonthlyServiceCharge::where('user_id', $user->id)
                ->latest('month')
                ->limit(1)
                ->first();
            $expected_amount = $monthly_service_charge;
            if ($last_monthly_service_charge && $last_monthly_service_charge->balance > 0) {
                $expected_amount = $last_monthly_service_charge->balance;
            }
            if ($this->userHasPaidFully($user_total_amount, $expected_amount)) {
                $status = MonthlyServiceChargeStatus::Paid;
                $amount_over_paid = $user_total_amount - $expected_amount;
                $balance = 0;
                $user_account_balance = $amount_over_paid;
                if ($amount_over_paid > 0) {
                    $status = MonthlyServiceChargeStatus::OverPaid;
                }
            } else {
                $balance = $expected_amount - $user_total_amount;
                $amount_over_paid = 0;
                $user_account_balance = -$balance;
                $status = MonthlyServiceChargeStatus::Balance;
            }
            try {
                DB::beginTransaction();
                MonthlyServiceCharge::create([
                    'user_id' => $user->id,
                    'service_charge' => $monthly_service_charge,
                    'amount_paid' => $amount_paid,
                    'balance' => $balance,
                    'credit' => $credit,
                    'amount_over_paid' => $amount_over_paid,
                    'month' => $month_to_bill,
                    'mpesa_transaction_id' => $mpesa_transaction_id,
                    'status' => $status
                ]);
                $bill_month_name = Str::lower(Carbon::createFromFormat('Y-m-d H:i:s', $month_to_bill)->format('M'));
                $monthly_service_charge_report = MonthlyServiceChargeReport::where('user_id', $user->id)
                    ->where('year', $month_to_bill->year)
                    ->first();
                $monthly_service_charge_report->update([
                    $bill_month_name => $balance
                ]);
                $user->update([
                    'account_balance' => $user_account_balance
                ]);
                $total_monthly_service_charge_paid += $amount_paid;
                DB::commit();
            } catch (Throwable $th) {
                Log::error($th);
            }
            $month_to_bill = $month_to_bill->add(1, 'month');
            $user_total_amount = 0;
            $amount_paid = 0;
            if ($balance > 0) {
                break;
            }

        }

        return $total_monthly_service_charge_paid;
    }

    public function getFirstMonthToBill($user)
    {
        $last_monthly_service_charge = MonthlyServiceCharge::where('user_id', $user->id)
            ->latest('month')
            ->limit(1)
            ->first();
        if ($last_monthly_service_charge) {
            return Carbon::createFromFormat('Y-m', $last_monthly_service_charge->month)->startOfMonth()->add(1, 'month');
        }
        return Carbon::createFromFormat('Y-m-d H:i:s', $user->first_monthly_service_fee_on);
    }

}
