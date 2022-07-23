<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Jobs\SendSMS;
use App\Models\MonthlyServiceCharge;
use App\Models\MonthlyServiceChargePayment;
use App\Models\MonthlyServiceChargeReport;
use App\Models\MpesaTransaction;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Log;
use Str;
use Throwable;

trait ProcessesMonthlyServiceChargeTransaction
{
    public function hasMonthlyServiceChargeDebt($user_id): bool
    {
        $last_monthly_service_charge = MonthlyServiceCharge::where('user_id', $user_id)
            ->latest()
            ->limit(1)
            ->first();
        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();

        if ($last_monthly_service_charge) {
            $last_monthly_service_charge_month = Carbon::createFromFormat('Y-m', $last_monthly_service_charge->month)->startOfMonth();
            return ($last_monthly_service_charge->status === PaymentStatus::NOT_PAID || $last_monthly_service_charge->status === PaymentStatus::PARTIALLY_PAID) && $last_monthly_service_charge_month->lessThanOrEqualTo($firstDayOfCurrentMonth);

        }

        return false;

    }

    public function userHasFundsInAccount($user): bool
    {
        return $user->account_balance > 0;
    }

    public function userHasPaidFully($user_total_amount, $expected_amount): bool
    {
        return $user_total_amount >= $expected_amount;
    }

    /**
     * @throws Throwable
     */
    public function storeMonthlyServiceCharge($user_id, $mpesa_transaction, $amount)
    {
        $user = User::findOrFail($user_id);
        $amount_paid = $amount;
        $user_total_amount = $amount_paid;
        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();
        $month_to_bill = $this->getFirstMonthToBill($user);
        $total_monthly_service_charge_paid = 0;

        while ($month_to_bill->lessThanOrEqualTo($firstDayOfCurrentMonth)) {
            $credit = 0;
            $user = $user->refresh();
            $monthly_service_charge = MonthlyServiceCharge::where('user_id', $user->id)
                ->where('month', $month_to_bill)
                ->first();
            if ($this->userHasFundsInAccount($user)) {
                $user_total_amount += $user->account_balance;
                $credit = $user->account_balance;
            }
            if ($user_total_amount <= 0 && $amount_paid === 0) {
                break;
            }
            $expected_amount = $monthly_service_charge->service_charge;
            if ($monthly_service_charge->status === PaymentStatus::PARTIALLY_PAID) {
                $monthly_service_charge_payment = MonthlyServiceChargePayment::where('monthly_service_charge_id', $monthly_service_charge->id)
                    ->latest('created_at')
                    ->take(1)
                    ->first();
                $expected_amount = $monthly_service_charge_payment->balance;
            }
            if ($this->userHasPaidFully($user_total_amount, $expected_amount)) {
                $status = PaymentStatus::PAID;
                $amount_over_paid = $user_total_amount - $expected_amount;
                $balance = 0;
                $user_account_balance = $amount_over_paid;
                $amount_to_deduct = $expected_amount;
                if ($amount_over_paid > 0) {
                    $status = PaymentStatus::OVER_PAID;
                }
            } else {
                $balance = $expected_amount - $user_total_amount;
                $amount_over_paid = 0;
                $user_account_balance = -$balance;
                $status = PaymentStatus::PARTIALLY_PAID;
                $amount_to_deduct = $user_total_amount;
            }
            try {
                DB::beginTransaction();
                MonthlyServiceChargePayment::create([
                    'monthly_service_charge_id' => $monthly_service_charge->id,
                    'amount_paid' => $amount_paid,
                    'balance' => $balance,
                    'credit' => $credit,
                    'amount_over_paid' => $amount_over_paid,
                    'mpesa_transaction_id' => $mpesa_transaction->id,
                ]);
                $monthly_service_charge->update([
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
                    'account_balance' => $user_account_balance,
                    'last_mpesa_transaction_id' => $mpesa_transaction->id
                ]);
                MpesaTransaction::find($mpesa_transaction->id)->update([
                    'Consumed' => true,
                ]);
                $total_monthly_service_charge_paid += $amount_to_deduct;
                DB::commit();
            } catch (Throwable $th) {
                DB::rollBack();
                Log::error($th);
            }
            $month_to_bill = $month_to_bill->add(1, 'month');
            $user_total_amount = 0;
            $amount_paid = 0;
            if ($balance > 0) {
                break;
            }

        }
        $total_monthly_service_charge_paid_formatted = number_format($total_monthly_service_charge_paid);

        $organization_name = env('APP_NAME');
        $message = "Your monthly service fee of Ksh $total_monthly_service_charge_paid_formatted has been received by $organization_name";
        SendSMS::dispatch($user->phone, $message, $user->id);

        return $total_monthly_service_charge_paid;
    }

    public function getFirstMonthToBill($user)
    {
        $last_monthly_service_charge = MonthlyServiceCharge::where('user_id', $user->id)
            ->where(function ($users) {
                $users->orWhere('status', PaymentStatus::NOT_PAID)
                    ->orWhere('status', PaymentStatus::PARTIALLY_PAID);
            })
            ->oldest()
            ->limit(1)
            ->first();
        return Carbon::createFromFormat('Y-m', $last_monthly_service_charge->month)->startOfMonth();
    }

}
