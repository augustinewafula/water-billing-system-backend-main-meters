<?php

namespace App\Traits;

use App\Enums\MeterReadingStatus;
use App\Http\Requests\CreateMeterBillingRequest;
use App\Jobs\SendSMS;
use App\Jobs\SwitchOnPaidMeter;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\MpesaTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Throwable;

trait ProcessesPostPaidTransaction
{
    use CalculatesBill, CalculatesUserTotalAmount, StoresMeterBillings;
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
        $organization_name = env('APP_NAME');
        $message = "Dear $mpesa_transaction->FirstName, your payment of Ksh $mpesa_transaction->TransAmount to $organization_name has been received. Thank you for being our esteemed customer.";
        SendSMS::dispatch($user->phone, $message, $user->user_id);
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
        $user_total_amount = $this->calculateUserTotalAmount($user->account_balance, $request->amount_paid, $request->monthly_service_charge_deducted);
        if ($user_total_amount < 0){
            return response()->json('Amount exhausted', 422);
        }
        $pending_meter_readings = MeterReading::where('meter_id', $request->meter_id)
            ->where(function ($query) {
                $query->where('status', MeterReadingStatus::NotPaid);
                $query->orWhere('status', MeterReadingStatus::Balance);
            })
            ->orderBy('created_at', 'ASC')->get();

        if ($pending_meter_readings->count() === 0) {
            $user->update([
                'account_balance' => $user_total_amount
            ]);
            return response()->json('Meter reading not found', 422);
        }

        $this->processMeterBillings($request, $pending_meter_readings, $user, $mpesa_transaction_id);
        SwitchOnPaidMeter::dispatch(Meter::find($request->meter_id));
        return response()->json('created', 201);
    }
}
