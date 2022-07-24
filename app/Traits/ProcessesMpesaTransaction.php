<?php

namespace App\Traits;

use App\Enums\UnresolvedMpesaTransactionReason;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use JsonException;
use Log;
use Throwable;

trait ProcessesMpesaTransaction
{
    use ProcessesPrepaidMeterTransaction, ProcessesPostPaidTransaction, ProcessesMonthlyServiceChargeTransaction, ProcessConnectionFeeTransaction,ProcessUnaccountedDebt;

    /**
     * @throws JsonException
     * @throws Throwable
     */
    private function processMpesaTransaction(MpesaTransaction $mpesa_transaction): void
    {
        $user = User::select('users.id as id', 'users.account_number', 'users.phone', 'users.communication_channels', 'users.email', 'users.first_monthly_service_fee_on', 'users.unaccounted_debt', 'users.should_pay_connection_fee', 'meters.id as meter_id', 'meters.number as meter_number', 'meter_types.name as meter_type_name', 'meter_stations.id as meter_station_id', 'meter_stations.name as meter_station_name')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->join('meter_stations', 'meters.station_id', 'meter_stations.id')
            ->leftJoin('meter_types', 'meter_types.id', 'meters.type_id')
            ->where('account_number', $mpesa_transaction->BillRefNumber)
            ->first();
        if (!$user) {
            UnresolvedMpesaTransaction::create([
                'mpesa_transaction_id' => $mpesa_transaction->id,
                'reason' => UnresolvedMpesaTransactionReason::INVALID_ACCOUNT_NUMBER
            ]);
            return;
        }
        $deductions = new Collection();
        $deductions->monthly_service_charge_deducted = 0;
        $deductions->unaccounted_debt_deducted = 0;
        $deductions->connection_fee_deducted = 0;

        if ($this->hasUnaccountedDebt($user->unaccounted_debt)){
            $unaccounted_debt_deducted = $this->processUnaccountedDebt($user->id, $mpesa_transaction);
            $deductions->unaccounted_debt_deducted = $unaccounted_debt_deducted;
            Log::info("Unaccounted debt deducted: {$unaccounted_debt_deducted}");
        }

//        if ($this->hasMonthlyServiceChargeDebt($user->id)) {
//            $monthly_service_charge_deducted = $this->storeMonthlyServiceCharge($user->id, $mpesa_transaction, $mpesa_transaction->TransAmount);
//                $deductions->monthly_service_charge = $monthly_service_charge_deducted;
//        }

        if ($user->should_pay_connection_fee && (($deductions->unaccounted_debt_deducted + $deductions->monthly_service_charge_deducted) < $mpesa_transaction->TransAmount) && !$this->hasCompletedConnectionFeePayment($user->id) && $this->hasMonthlyConnectionFeeDebt($user->id)) {
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
}
