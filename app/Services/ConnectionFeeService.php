<?php

namespace App\Services;

use App\Actions\GenerateConnectionFeeAction;
use App\Enums\PaymentStatus;
use App\Models\ConnectionFee;
use App\Models\ConnectionFeePayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Throwable;

class ConnectionFeeService
{
    /**
     * @throws Throwable
     */
    public function generate($user): void
    {
        (new GenerateConnectionFeeAction)->execute($user);
    }

    public function destroyAll($user): void
    {
        \Log::info('Deleting all connection fees for user: ' . $user->id);
        $connection_fees = ConnectionFee::where('user_id', $user->id)->get();
        foreach ($connection_fees as $connection_fee) {
            $this->destroy($connection_fee);
        }
    }

    public function destroy(ConnectionFee $connectionFee): void
    {
        $currentMonth = Carbon::now()->startOfMonth()->startOfDay();
        $connectionFeeMonth = Carbon::createFromFormat('Y-m', $connectionFee->month)->startOfMonth()->startOfDay();

        if ($connectionFeeMonth->lessThanOrEqualTo($currentMonth)){
            \Log::info('Removing connection fee amount for user: ' . $connectionFee->user_id . ' for month: ' . $connectionFee->month);
            $this->removeConnectionFeeBillFromUserAccount($connectionFee);
        }

        $connectionFee->forceDelete();
    }

    public function hasConnectionFeeBeenUpdated($user, $connectionFee, $numberOfMonthsToPay): bool
    {

        return $user->number_of_months_to_pay_connection_fee !== $numberOfMonthsToPay && $user->connection_fee !== $connectionFee;

    }

    public function addConnectionFeeBillToUserAccount($connectionFee): void
    {
        if ($connectionFee->status === PaymentStatus::Paid || $connectionFee->status === PaymentStatus::OverPaid) {
            return;
        }
        $user = User::findOrFail($connectionFee->user_id);

        if ($connectionFee->status === PaymentStatus::Balance) {
            $partialPayments = ConnectionFeePayment::where('connection_fee_id', $connectionFee->id)->get();
            $partialPaymentAmount = 0;
            foreach ($partialPayments as $partialPayment) {
                $partialPaymentAmount += $partialPayment->amount_paid;
            }

            $user_total_amount = $user->account_balance - $partialPaymentAmount;
            $user->update(['account_balance' => $user_total_amount]);

            return;
        }


        $user_total_amount = $user->account_balance - $connectionFee->amount;
        $user->update(['account_balance' => $user_total_amount]);

    }

    private function removeConnectionFeeBillFromUserAccount($connectionFee): void
    {
        $user = User::findOrFail($connectionFee->user_id);
        if ($connectionFee->status === PaymentStatus::NotPaid){
            $user_total_amount = $user->account_balance + $connectionFee->amount;
            \Log::info('account_balance: ' . $user->account_balance . 'Connection fee amount: ' . $connectionFee->amount . 'Total amount: ' . $user_total_amount);
            $user->update(['account_balance' => $user_total_amount]);

            return;
        }

        if ($connectionFee->status === PaymentStatus::Balance || $connectionFee->status === PaymentStatus::OverPaid || $connectionFee->status === PaymentStatus::Paid){
            $connection_fee_payments = ConnectionFeePayment::where('meter_reading_id', $connectionFee->id)->get();
            $user_total_amount = $user->account_balance;
            foreach ($connection_fee_payments as $connection_fee_payment){
                if ($connection_fee_payment->amount_paid > 0){
                    $actual_meter_reading_amount_paid = $connection_fee_payment->amount_paid - ($connection_fee_payment->monthly_service_charge_deducted);
                }else{
                    $actual_meter_reading_amount_paid = $connection_fee_payment->credit - ($connection_fee_payment->monthly_service_charge_deducted);
                }
                if ($connectionFee->status === PaymentStatus::Balance || $connectionFee->status === PaymentStatus::Paid){
                    $user_total_amount += $actual_meter_reading_amount_paid;

                    continue;
                }
                if ($connectionFee->status === PaymentStatus::OverPaid){
                    $user_total_amount += ($actual_meter_reading_amount_paid - $connection_fee_payment->amount_over_paid);
                }
            }
            $user->update(['account_balance' => $user_total_amount]);
        }

    }

}
