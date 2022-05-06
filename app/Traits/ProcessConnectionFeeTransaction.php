<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Jobs\SendSMS;
use App\Models\ConnectionFee;
use App\Models\ConnectionFeeCharge;
use App\Models\ConnectionFeePayment;
use App\Models\MpesaTransaction;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Log;
use Throwable;

trait ProcessConnectionFeeTransaction
{
    public function hasMonthlyConnectionFeeDebt($user_id): bool
    {
        $last_connection_fee = ConnectionFee::where('user_id', $user_id)
            ->latest()
            ->limit(1)
            ->first();
        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();

        if ($last_connection_fee) {
            $last_connection_fee_month = Carbon::createFromFormat('Y-m', $last_connection_fee->month)->startOfMonth();
            return ($last_connection_fee->status === PaymentStatus::NotPaid || $last_connection_fee->status === PaymentStatus::Balance) && $last_connection_fee_month->lessThanOrEqualTo($firstDayOfCurrentMonth);

        }

        return false;

    }

    public function hasCompletedConnectionFeePayment($user_id): bool
    {
        $user = User::where('id', $user_id)
            ->with('meter')
            ->firstOrFail();
        $connection_fee_charges = ConnectionFeeCharge::where('station_id', $user->meter->station_id)
            ->first();
        $connection_fee_per_month = $connection_fee_charges->connection_fee_monthly_installment;
        return $user->total_connection_fee_paid === $connection_fee_per_month;
    }

    public function userHasFunds($user): bool
    {
        return $user->account_balance > 0;
    }

    public function userHasPaidInFull($user_total_amount, $expected_amount): bool
    {
        return $user_total_amount >= $expected_amount;
    }

    /**
     * @throws Throwable
     */
    public function storeConnectionFee($user_id, $mpesa_transaction, $amount, $monthly_service_charge_deducted)
    {
        $user = User::findOrFail($user_id);
        $amount_paid = $amount;
        $user_total_amount = $amount_paid;
        $firstDayOfCurrentMonth = Carbon::now()->startOfMonth();
        $month_to_bill = $this->getMonthToBill($user);
        $total_connection_fee_paid = 0;

        if ($monthly_service_charge_deducted > 0){
            $user_total_amount = 0;
        }

        while ($month_to_bill->lessThanOrEqualTo($firstDayOfCurrentMonth)) {
            $credit = 0;
            $user = $user->refresh();
            $connection_fee = ConnectionFee::where('user_id', $user->id)
                ->where('month', $month_to_bill)
                ->first();
            if ($this->userHasFunds($user)) {
                $user_total_amount += $user->account_balance;
                $credit = $user->account_balance;
            }
            if ($user_total_amount <= 0 && $amount_paid === 0) {
                break;
            }
            $expected_amount = $connection_fee->amount;
            if ($connection_fee->status === PaymentStatus::Balance) {
                $connection_fee_payment = ConnectionFeePayment::where('connection_fee_id', $connection_fee->id)
                    ->latest('created_at')
                    ->take(1)
                    ->first();
                $expected_amount = $connection_fee_payment->balance;
            }
            if ($this->userHasPaidInFull($user_total_amount, $expected_amount)) {
                $status = PaymentStatus::Paid;
                $amount_over_paid = $user_total_amount - $expected_amount;
                $balance = 0;
                $user_account_balance = $amount_over_paid;
                $amount_to_deduct = $expected_amount;
                if ($amount_over_paid > 0) {
                    $status = PaymentStatus::OverPaid;
                }
            } else {
                $balance = $expected_amount - $user_total_amount;
                $amount_over_paid = 0;
                $user_account_balance = -$balance;
                $status = PaymentStatus::Balance;
                $amount_to_deduct = $user_total_amount;
            }
            try {
                DB::beginTransaction();
                ConnectionFeePayment::create([
                    'connection_fee_id' => $connection_fee->id,
                    'amount_paid' => $amount_paid,
                    'balance' => $balance,
                    'credit' => $credit,
                    'amount_over_paid' => $amount_over_paid,
                    'monthly_service_charge_deducted' => $monthly_service_charge_deducted,
                    'mpesa_transaction_id' => $mpesa_transaction->id,
                ]);
                $connection_fee->update([
                    'status' => $status
                ]);
                $user->update([
                    'account_balance' => $user_account_balance,
                    'last_mpesa_transaction_id' => $mpesa_transaction->id
                ]);
                MpesaTransaction::find($mpesa_transaction->id)->update([
                    'Consumed' => true,
                ]);
                $total_connection_fee_paid += $amount_to_deduct;
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
            $monthly_service_charge_deducted = 0;

        }
        $user->update([
            'total_connection_fee_paid' => $user->account_balance + $total_connection_fee_paid,
        ]);

        $organization_name = env('APP_NAME');
        $message = "Your connection fee of Ksh $total_connection_fee_paid has been received by $organization_name";
        SendSMS::dispatch($user->phone, $message, $user->id);

        return $total_connection_fee_paid;
    }

    public function getMonthToBill($user)
    {
        $last_connection_fee = ConnectionFee::where('user_id', $user->id)
            ->where(function ($users) {
                $users->orWhere('status', PaymentStatus::NotPaid)
                    ->orWhere('status', PaymentStatus::Balance);
            })
            ->oldest()
            ->limit(1)
            ->first();
        return Carbon::createFromFormat('Y-m', $last_connection_fee->month)->startOfMonth();
    }

}
