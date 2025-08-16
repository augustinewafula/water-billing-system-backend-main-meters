<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Jobs\SendSMS;
use App\Models\MonthlyServiceCharge;
use App\Models\MonthlyServiceChargePayment;
use App\Models\MpesaTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MonthlyServiceChargeService
{
    public function hasMonthlyServiceChargeDebt(string $user_id): bool
    {
        $lastCharge = \App\Models\MonthlyServiceCharge::where('user_id', $user_id)->latest()->first();
        if (!$lastCharge) return false;

        $chargeMonth = Carbon::parse($lastCharge->month)->startOfMonth();
        $currentMonth = now()->startOfMonth();

        return in_array($lastCharge->status, [
                PaymentStatus::NOT_PAID,
                PaymentStatus::PARTIALLY_PAID,
            ], true) && $chargeMonth->lessThanOrEqualTo($currentMonth);
    }

    public function userHasFundsInAccount(User $user): bool
    {
        return $user->account_balance > 0;
    }

    public function userHasPaidFully(float $userTotalAmount, float $expectedAmount): bool
    {
        return $userTotalAmount >= $expectedAmount;
    }

    public function getFirstMonthToBill(User $user): ?Carbon
    {
        Log::debug("Determining first month to bill for user", ['user_id' => $user->id]);

        $charge = MonthlyServiceCharge::where('user_id', $user->id)
            ->whereIn('status', [PaymentStatus::NOT_PAID, PaymentStatus::PARTIALLY_PAID])
            ->oldest()
            ->first();

        if (!$charge || !$charge->month) {
            Log::debug("No unpaid or partially paid charges found", ['user_id' => $user->id]);
            return null;
        }

        return Carbon::parse($charge->month)->startOfMonth();
    }

    protected function calculateExpectedAmount(MonthlyServiceCharge $charge): float
    {
        if ($charge->status !== PaymentStatus::PARTIALLY_PAID) {
            return $charge->service_charge;
        }

        $lastPayment = MonthlyServiceChargePayment::where('monthly_service_charge_id', $charge->id)
            ->latest('created_at')
            ->first();

        return $lastPayment?->balance ?? $charge->service_charge;
    }

    /**
     * @throws Throwable
     */
    public function storeMonthlyServiceCharge(string $user_id, MpesaTransaction $mpesa_transaction, float $amount): float
    {
        Log::info("Starting monthly service charge processing", compact('user_id', 'mpesa_transaction', 'amount'));

        $user = User::findOrFail($user_id);
        $firstMonthToBill = $this->getFirstMonthToBill($user);
        $currentMonth = now()->startOfMonth();

        if (!$firstMonthToBill || $firstMonthToBill->greaterThan($currentMonth)) {
            Log::debug("No valid month to bill", ['user_id' => $user_id]);
            return 0;
        }

        $totalPaid = 0;
        $remainingFunds = $amount;

        Log::debug("Initial billing parameters", [
            'user_account_balance' => $user->account_balance,
            'first_month_to_bill' => $firstMonthToBill->format('Y-m'),
            'current_month' => $currentMonth->format('Y-m')
        ]);

        $billingMonth = $firstMonthToBill->copy();

        while ($billingMonth->lessThanOrEqualTo($currentMonth)) {
            $monthlyCharge = MonthlyServiceCharge::where('user_id', $user->id)
                ->whereDate('month', $billingMonth->startOfMonth())
                ->first();

            if (!$monthlyCharge) {
                Log::debug("No charge found for month", ['month' => $billingMonth->format('Y-m')]);
                $billingMonth->addMonth();
                continue;
            }

            $user = $user->refresh();
            $credit = $this->userHasFundsInAccount($user) ? $user->account_balance : 0;
            $userTotal = $remainingFunds + $credit;

            if ($userTotal <= 0) {
                Log::debug("Breaking - no funds available", ['month' => $billingMonth->format('Y-m')]);
                break;
            }

            $expected = $this->calculateExpectedAmount($monthlyCharge);

            $status = PaymentStatus::PARTIALLY_PAID;
            $balance = $expected - $userTotal;
            $amountToDeduct = $userTotal;
            $overpaid = 0;
            $newBalance = 0;

            if ($this->userHasPaidFully($userTotal, $expected)) {
                $status = $userTotal > $expected ? PaymentStatus::OVER_PAID : PaymentStatus::PAID;
                $overpaid = max(0, $userTotal - $expected);
                $balance = 0;
                $amountToDeduct = $expected;
            } else {
                $newBalance = -$balance;
            }

            try {
                DB::beginTransaction();

                $payment = MonthlyServiceChargePayment::create([
                    'monthly_service_charge_id' => $monthlyCharge->id,
                    'amount_paid' => min($remainingFunds, $monthlyCharge->service_charge),
                    'balance' => $balance,
                    'credit' => $credit,
                    'amount_over_paid' => $overpaid,
                    'mpesa_transaction_id' => $mpesa_transaction->id,
                ]);

                $monthlyCharge->update(['status' => $status]);
                //No need to update user account balance, monthly service charge debt is tracked separately
                $user->update([
                    'last_mpesa_transaction_id' => $mpesa_transaction->id
                ]);

                $mpesa_transaction->update(['Consumed' => true]);

                DB::commit();

                Log::info("Payment processed for month", [
                    'month' => $billingMonth->format('Y-m'),
                    'payment_id' => $payment->id,
                    'amount_applied' => $amountToDeduct
                ]);

                $totalPaid += $amountToDeduct;
                $remainingFunds = 0;
            } catch (Throwable $e) {
                DB::rollBack();
                Log::error("Failed processing payment", [
                    'month' => $billingMonth->format('Y-m'),
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            if ($balance > 0) {
                Log::debug("Stopping - remaining balance exists", ['balance' => $balance]);
                break;
            }

            $billingMonth->addMonth();
        }

        $monthsProcessed = $billingMonth->diffInMonths($firstMonthToBill);
        Log::info("Monthly service charge processing completed", [
            'total_amount_paid' => $totalPaid,
            'months_processed' => $monthsProcessed
        ]);

        $formattedAmount = number_format($totalPaid);
        $message = "Your monthly service fee of Ksh $formattedAmount has been received by " . env('APP_NAME');
        SendSMS::dispatch($user->phone, $message, $user->id);

        return $totalPaid;
    }

}
