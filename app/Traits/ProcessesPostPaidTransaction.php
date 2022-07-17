<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
use App\Http\Requests\CreateMeterBillingRequest;
use App\Jobs\SendSMS;
use App\Jobs\SwitchOnPaidMeter;
use App\Models\CreditAccount;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\MpesaTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Throwable;

trait ProcessesPostPaidTransaction
{
    use CalculatesBill, CalculatesUserAmount, StoresMeterBillings, NotifiesUser;

    /**
     * @param $user
     * @param MpesaTransaction $mpesa_transaction
     * @param $monthly_service_charge_deducted
     * @param $connection_fee_deducted
     * @param $unaccounted_debt_deducted
     * @return void
     * @throws Throwable
     */
    private function processPostPaidTransaction($user, MpesaTransaction $mpesa_transaction, $monthly_service_charge_deducted, $connection_fee_deducted, $unaccounted_debt_deducted): void
    {
        $request = new CreateMeterBillingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $user->meter_id,
            'amount_paid' => $mpesa_transaction->TransAmount,
            'monthly_service_charge_deducted' => $monthly_service_charge_deducted,
            'connection_fee_deducted' => $connection_fee_deducted,
            'unaccounted_debt_deducted' => $unaccounted_debt_deducted
        ]);
        $mpesa_transaction_amount_formatted = number_format($mpesa_transaction->TransAmount);
        $organization_name = env('APP_NAME');
        $message = "Dear $mpesa_transaction->FirstName, your payment of Ksh $mpesa_transaction_amount_formatted to $organization_name has been received. Thank you for being our esteemed customer.";
        $this->notifyUser((object)['message' => $message, 'title' => 'Payment received'], $user, 'general');
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
        $user = User::where('meter_id', $request->meter_id)->firstOrFail();

        $user_total_amount = $this->calculateUserTotalAmount($user->account_balance, $request->amount_paid, $request->monthly_service_charge_deducted, $request->connection_fee_deducted, $request->unaccounted_debt_deducted);
        \Log::info('User total amount: '. $user_total_amount);
        if ($user_total_amount <= 0){
            return response()->json('Amount exhausted', 422);
        }
        $pending_meter_readings = MeterReading::where('meter_id', $request->meter_id)
            ->where(function ($query) {
                $query->where('status', PaymentStatus::NotPaid);
                $query->orWhere('status', PaymentStatus::Balance);
            })
            ->orderBy('created_at', 'ASC')->get();

        \Log::info('Pending meter readings count: '. $pending_meter_readings->count());

        if ($pending_meter_readings->count() === 0) {
            $user->update([
                'account_balance' => $user_total_amount
            ]);
            if ($request->monthly_service_charge_deducted === 0 && $request->connection_fee_deducted === 0 && $request->unaccounted_debt_deducted === 0) {
                CreditAccount::create([
                    'user_id' => $user->id,
                    'amount' => $request->amount_paid,
                    'mpesa_transaction_id' => $mpesa_transaction_id,
                ]);
            }
            return response()->json('Meter reading not found', 422);
        }

        $this->processMeterBillings($request, $pending_meter_readings, $user, $mpesa_transaction_id, $user_total_amount);
        SwitchOnPaidMeter::dispatch(Meter::find($request->meter_id));
        \Log::info("Switching on paid meter id: $request->meter_id");
        return response()->json('created', 201);
    }
}
