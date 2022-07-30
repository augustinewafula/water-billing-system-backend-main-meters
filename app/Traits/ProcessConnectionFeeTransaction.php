<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Jobs\SendSMS;
use App\Models\ConnectionFee;
use App\Models\ConnectionFeePayment;
use App\Models\MpesaTransaction;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Log;
use Throwable;

trait ProcessConnectionFeeTransaction
{
    use CalculatesUserAmount;

    public function hasMonthlyConnectionFeeDebt($user_id): bool
    {
        $connection_fees = ConnectionFee::where('user_id', $user_id)
            ->currentAndPreviousMonth()
            ->notPaid()
            ->orWhere
            ->hasBalance()
            ->get();

        return $connection_fees->count() > 0;

    }

    public function hasCompletedConnectionFeePayment($user_id): bool
    {
        $user = User::where('id', $user_id)
            ->with('meter')
            ->firstOrFail();

        return $user->total_connection_fee_paid >= $user->connection_fee;
    }

    public function getUserMonthlyConnectionFeeBill($user_id): float
    {
        return ConnectionFee::where('user_id', $user_id)
            ->latest()
            ->limit(1)
            ->firstOrFail()
            ->amount;
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
    public function storeConnectionFeeBill($user_id, $mpesa_transaction, $amount, $deductions, $paidToMeterConnectionAccount = false)
    {
        $user = User::findOrFail($user_id);
        $amount_paid = $amount;
        $user_total_amount = $this->calculateUserTotalAmount($user->account_balance, $amount_paid, $deductions);
        $lastMonthToBill = Carbon::now()->startOfMonth();
        $month_to_bill = $this->getMonthToBill($user);
        $total_connection_fee_paid = 0;

        if ($paidToMeterConnectionAccount){
            $lastMonthToBill = $this->getLastMonthToBill($user_id, $lastMonthToBill);
        }
        Log::info('Storing connection fee bill for user: ' . $user_id);
        Log::info('Last month to bill: ' . $lastMonthToBill->format('Y-m-d'));
        Log::info('user_total_amount: ' . $user_total_amount);

        while ($month_to_bill->lessThanOrEqualTo($lastMonthToBill)) {
            $credit = 0;
            $user = $user->refresh();
            $connection_fee = ConnectionFee::where('user_id', $user->id)
                ->where('month', $month_to_bill)
                ->first();
            if ($this->userHasFunds($user)) {
                $credit = $user->account_balance;
            }
            $deductions_sum = $deductions->monthly_service_charge_deducted + $deductions->unaccounted_debt_deducted;
            if ($deductions_sum > 0){
                $credit += $amount_paid - $deductions_sum;
                $amount_paid = 0;
            }
            if ($user_total_amount <= 0 && $amount_paid === 0) {
                break;
            }
            $expected_amount = $connection_fee->amount;
            if ($connection_fee->status === PaymentStatus::PARTIALLY_PAID) {
                $connection_fee_payment = ConnectionFeePayment::where('connection_fee_id', $connection_fee->id)
                    ->latest('created_at')
                    ->take(1)
                    ->first();
                $expected_amount = $connection_fee_payment->balance;
            }
            if ($this->userHasPaidInFull($user_total_amount, $expected_amount)) {
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
                $mpesa_transaction_id = null;
                if (is_object($mpesa_transaction)){
                    $mpesa_transaction_id = $mpesa_transaction->id;
                }
                    ConnectionFeePayment::create([
                    'connection_fee_id' => $connection_fee->id,
                    'amount_paid' => $amount_paid,
                    'balance' => $balance,
                    'credit' => abs($credit),
                    'amount_over_paid' => $amount_over_paid,
                    'monthly_service_charge_deducted' => $deductions->monthly_service_charge_deducted,
                    'unaccounted_debt_deducted' => $deductions->unaccounted_debt_deducted,
                    'mpesa_transaction_id' => $mpesa_transaction_id,
                ]);
                $connection_fee->update([
                    'status' => $status
                ]);
                $user->update([
                    'account_balance' => $user_account_balance,
                    'last_mpesa_transaction_id' => $mpesa_transaction_id
                ]);
                if (is_object($mpesa_transaction)) {
                    MpesaTransaction::find($mpesa_transaction_id)->update([
                        'Consumed' => true,
                    ]);
                }
                $total_connection_fee_paid += $amount_to_deduct;
                DB::commit();
            } catch (Throwable $th) {
                DB::rollBack();
                Log::error($th);
            }
            $month_to_bill = $month_to_bill->add(1, 'month');
            $user_total_amount -= $amount_to_deduct;
            $amount_paid = 0;

        }
        $user->update([
            'total_connection_fee_paid' => $user->total_connection_fee_paid + $total_connection_fee_paid,
        ]);
        $total_connection_fee_paid_formatted = number_format($total_connection_fee_paid);

        $organization_name = env('APP_NAME');
        $message = "Your connection fee of Ksh $total_connection_fee_paid_formatted has been received by $organization_name";
        SendSMS::dispatch($user->phone, $message, $user->id);

        return $total_connection_fee_paid;
    }

    public function getMonthToBill($user): Carbon
    {
        $last_connection_fee = ConnectionFee::where('user_id', $user->id)
            ->where(function ($users) {
                $users->orWhere('status', PaymentStatus::NOT_PAID)
                    ->orWhere('status', PaymentStatus::PARTIALLY_PAID);
            })
            ->oldest()
            ->limit(1)
            ->first();
        return Carbon::createFromFormat('Y-m', $last_connection_fee->month)->startOfMonth();
    }

    /**
     * @param $user_id
     * @param bool|Carbon $lastMonthToBill
     * @return Carbon
     */
    private function getLastMonthToBill($user_id, bool|Carbon $lastMonthToBill): Carbon
    {
        $lastBill = ConnectionFee::where('user_id', $user_id)
            ->latest()
            ->limit(1)
            ->first();
        if ($lastBill) {
            $lastMonthToBill = Carbon::create($lastBill->month)->startOfMonth();
        }
        return $lastMonthToBill;
    }

}
