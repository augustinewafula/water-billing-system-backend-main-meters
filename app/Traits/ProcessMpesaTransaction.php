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
use Throwable;

trait ProcessMpesaTransaction
{
    use ProcessPrepaidMeterTransaction, CalculatesBill, StoreMeterBillings, ProcessMonthlyServiceChargeTransaction;

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
            $monthly_service_charge_deducted = $this->storeMonthlyServiceCharge($user->user_id, $mpesa_transaction);
        }

        if ($user->meter_type_name === 'Prepaid') {
            $this->processPrepaidTransaction($user->meter_id, $mpesa_transaction, $monthly_service_charge_deducted);
            return;

        }

        $this->processPostPaidTransaction($user, $mpesa_transaction, $monthly_service_charge_deducted);
    }


    /**
     * @param $user
     * @param MpesaTransaction $mpesa_transaction
     * @param $monthly_service_charge_deducted
     * @return void
     * @throws Throwable
     */
    private function processPostPaidTransaction($user, MpesaTransaction $mpesa_transaction, $monthly_service_charge_deducted): void
    {
        $request = new CreateMeterBillingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $user->meter_id,
            'amount_paid' => $mpesa_transaction->TransAmount,
            'monthly_service_charge_deducted' => $monthly_service_charge_deducted
        ]);
        //TODO::make organization name dynamic
        $message = "Dear $mpesa_transaction->FirstName $mpesa_transaction->LastName, your payment of Ksh $mpesa_transaction->TransAmount to Progressive Utility has been received. Thank you for being our esteemed customer.";
        SendSMS::dispatch($mpesa_transaction->MSISDN, $message, $user->user_id);

        $this->store($request, $mpesa_transaction->id);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateMeterBillingRequest $request
     * @param $mpesa_transaction_id
     * @return JsonResponse
     * @throws Throwable
     */
    public function store(CreateMeterBillingRequest $request, $mpesa_transaction_id): JsonResponse
    {
        $user = User::where('meter_id', $request->meter_id)->first();
        if (!$user){
            return response()->json('User not found', 422);
        }
        $pending_meter_readings = MeterReading::where('meter_id', $request->meter_id)
            ->where(function ($query) {
                $query->where('status', MeterReadingStatus::NotPaid);
                $query->orWhere('status', MeterReadingStatus::Balance);
            })
            ->orderBy('created_at', 'ASC')->get();

        if ($pending_meter_readings->count() === 0) {
            $user_account_balance = 0;
            if ($user->account_balance > 0){
                $user_account_balance += $user->account_balance;
                $user->update([
                    'account_balance' => $user_account_balance
                ]);
            }
            return response()->json('Meter reading not found', 422);
        }

        $this->processMeterBillings($request, $pending_meter_readings, $user, $mpesa_transaction_id);
        SwitchOnPaidMeter::dispatch(Meter::find($request->meter_id));
        return response()->json('created', 201);
    }
}
