<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Enums\UnresolvedMpesaTransactionReason;
use App\Http\Requests\CreateMeterBillingRequest;
use App\Jobs\SendSMS;
use App\Jobs\SwitchOnPaidMeter;
use App\Models\ConnectionFeeCharge;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\MpesaTransaction;
use App\Models\Setting;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use JsonException;
use Log;
use Throwable;

trait ProcessesMpesaTransaction
{
    use ProcessesPrepaidMeterTransaction, ProcessesPostPaidTransaction, ProcessesMonthlyServiceChargeTransaction, ProcessConnectionFeeTransaction;

    /**
     * @throws JsonException
     * @throws Throwable
     */
    private function processMpesaTransaction(MpesaTransaction $mpesa_transaction): void
    {
        $user = User::select('users.id as user_id', 'users.account_number', 'users.phone', 'users.first_monthly_service_fee_on', 'users.should_pay_connection_fee', 'meters.id as meter_id', 'meters.number as meter_number', 'meter_types.name as meter_type_name')
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
//        if ($this->hasMonthlyServiceChargeDebt($user->user_id)) {
//            $monthly_service_charge_deducted = $this->storeMonthlyServiceCharge($user->user_id, $mpesa_transaction, $mpesa_transaction->TransAmount);
//        }

        $connection_fee_deducted = 0;
        if ($user->should_pay_connection_fee && ($monthly_service_charge_deducted < $mpesa_transaction->TransAmount)){
            $user = User::where('id', $user->user_id)
                ->with('meter')
                ->firstOrFail();
            $connection_fee_charges = ConnectionFeeCharge::where('station_id', $user->meter->station_id)
                ->first();
            $connection_fee = $connection_fee_charges->connection_fee;
            if ($user->total_connection_fee_paid < $connection_fee && $this->hasMonthlyConnectionFeeDebt($user->id)){
                $connection_fee_deducted = $this->storeConnectionFee($user->id, $mpesa_transaction, $mpesa_transaction->TransAmount, $monthly_service_charge_deducted);
            }
        }

        if ($user->meter_type_name === 'Prepaid') {
            $this->processPrepaidTransaction($user->meter_id, $mpesa_transaction, $monthly_service_charge_deducted, $connection_fee_deducted);
            return;

        }

        $this->processPostPaidTransaction($user, $mpesa_transaction, $monthly_service_charge_deducted, $connection_fee_deducted);
    }
}
