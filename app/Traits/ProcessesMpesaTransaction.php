<?php

namespace App\Traits;

use App\Enums\UnresolvedMpesaTransactionReason;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

trait ProcessesMpesaTransaction
{
    use ProcessesPrepaidMeterTransaction,
        ProcessesPostPaidTransaction,
        ProcessesMonthlyServiceChargeTransaction,
        ProcessConnectionFeeTransaction,
        ProcessUnaccountedDebt,
        initializesDeductionsAmount,
        NotifiesNewPayment;

    /**
     * @throws JsonException
     * @throws Throwable
     */
    private function processMpesaTransaction(MpesaTransaction $mpesa_transaction): void
    {
        Log::info('Processing mpesa transaction: ' . $mpesa_transaction->TransID . ' for ' . $mpesa_transaction->MSISDN. ' with amount: ' . $mpesa_transaction->TransAmount. ' and account: ' . $mpesa_transaction->BillRefNumber);
        $this->notifyNewPayment($mpesa_transaction);
        $user = $this->getUser($mpesa_transaction->BillRefNumber);

        $deductions = $this->initializeDeductions();

        if (!$user && $account_number = $this->isPaymentForMeterConnectionAccount($mpesa_transaction)) {
            $user = $this->getUser($account_number);
            if ($user && !$this->hasCompletedConnectionFeePayment($user->id)) {
                $connection_fee_deducted = $this->storeConnectionFeeBill($user->id, $mpesa_transaction, $mpesa_transaction->TransAmount, $deductions, true,true);
                $deductions->connection_fee_deducted = $connection_fee_deducted;
                Log::info("connection_fee_deducted: $connection_fee_deducted");

                return;

            }
        }

        if (!$user) {
            UnresolvedMpesaTransaction::create([
                'mpesa_transaction_id' => $mpesa_transaction->id,
                'reason' => UnresolvedMpesaTransactionReason::INVALID_ACCOUNT_NUMBER
            ]);

            return;
        }

        if ($this->hasUnaccountedDebt($user->unaccounted_debt)){
            $unaccounted_debt_deducted = $this->processUnaccountedDebt($user->id, $mpesa_transaction);
            $deductions->unaccounted_debt_deducted = $unaccounted_debt_deducted;
            Log::info("Unaccounted debt deducted: {$unaccounted_debt_deducted}");
        }

//        if ($this->hasMonthlyServiceChargeDebt($user->id)) {
//            $monthly_service_charge_deducted = $this->storeMonthlyServiceCharge($user->id, $mpesa_transaction, $mpesa_transaction->TransAmount);
//                $deductions->monthly_service_charge = $monthly_service_charge_deducted;
//        }

        if ($user->should_pay_connection_fee && (($deductions->unaccounted_debt_deducted + $deductions->monthly_service_charge_deducted) < $mpesa_transaction->TransAmount) && $this->hasMonthlyConnectionFeeDebt($user->id)) {
            $connection_fee_deducted = $this->storeConnectionFeeBill($user->id, $mpesa_transaction, $mpesa_transaction->TransAmount, $deductions);
            $deductions->connection_fee_deducted = $connection_fee_deducted;
            Log::info("connection_fee_deducted: $connection_fee_deducted");
        }

        if ($user->meter_type_name === 'Prepaid') {
            $this->processPrepaidTransaction($user->meter_id, $mpesa_transaction, $deductions);
            return;

        }

        $this->processPostPaidTransaction($user, $mpesa_transaction, $deductions);
    }

    /**
     * @param MpesaTransaction $mpesa_transaction
     * @return array|bool
     */
    private function isPaymentForMeterConnectionAccount(MpesaTransaction $mpesa_transaction): string|bool
    {
        $account_number = $mpesa_transaction->BillRefNumber;
        $contains_underscore = Str::of($account_number)->contains('_');
        $contains_hyphen = Str::of($account_number)->contains('-');
        if ($contains_underscore || $contains_hyphen) {
            $separator = '-';
            if ($contains_underscore) {
                $separator = '_';
            }
            $new_account_number = Str::before($account_number, $separator);
            $constant_word = Str::after($account_number, $separator);
            $constant_word = Str::of($constant_word)->lower();

            if ($constant_word == 'meter' || $constant_word == 'mita') {
                return $new_account_number;
            }
        }

        return false;
    }

    /**
     * @param string $account_number
     * @return Builder|Model|null
     */
    private function getUser(string $account_number): Builder|null|Model
    {
        return User::select('users.id as id', 'users.account_number', 'users.phone', 'users.communication_channels', 'users.email', 'users.first_monthly_service_fee_on', 'users.unaccounted_debt', 'users.should_pay_connection_fee', 'meters.id as meter_id', 'meters.number as meter_number', 'meter_types.name as meter_type_name', 'meter_stations.id as meter_station_id', 'meter_stations.name as meter_station_name')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meters.station_id', 'meter_stations.id')
            ->leftJoin('meter_types', 'meter_types.id', 'meters.type_id')
            ->where('account_number', $account_number)
            ->first();
    }

}
