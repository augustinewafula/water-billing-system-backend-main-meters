<?php

namespace App\Traits;

use App\Enums\MeterReadingStatus;
use App\Enums\UnresolvedMpesaTransactionReason;
use App\Http\Requests\CreateMeterBillingRequest;
use App\Jobs\SendSMS;
use App\Jobs\SwitchOnPaidMeter;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use JsonException;
use Log;
use Throwable;

trait ProcessesMpesaTransaction
{
    use ProcessesPrepaidMeterTransaction, ProcessesPostPaidTransaction, ProcessesMonthlyServiceChargeTransaction;

    /**
     * @throws JsonException
     * @throws Throwable
     */
    private function processMpesaTransaction(MpesaTransaction $mpesa_transaction): void
    {
        $user = User::select('users.id as user_id', 'users.account_number', 'users.first_monthly_service_fee_on', 'meters.id as meter_id', 'meters.number as meter_number', 'meter_types.name as meter_type_name')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->leftJoin('meter_types', 'meter_types.id', 'meters.type_id')
            ->where('account_number', $mpesa_transaction->BillRefNumber)
            ->first();
        if (!$user) {
            UnresolvedMpesaTransaction::create([
                'mpesa_transaction_id' => $mpesa_transaction->id,
                'reason' => UnresolvedMpesaTransactionReason::InvalidAccountNumber
            ]);
            return;
        }

        $monthly_service_charge_deducted = 0;
        if ($this->hasMonthlyServiceChargeDebt($user->user_id)) {
            $monthly_service_charge_deducted = $this->storeMonthlyServiceCharge($user->user_id, $mpesa_transaction, $mpesa_transaction->TransAmount);
        }

        if ($user->meter_type_name === 'Prepaid') {
            $this->processPrepaidTransaction($user->meter_id, $mpesa_transaction, $monthly_service_charge_deducted);
            return;

        }

        $this->processPostPaidTransaction($user, $mpesa_transaction, $monthly_service_charge_deducted);
    }
}
