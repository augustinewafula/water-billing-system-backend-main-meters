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
     * @param $deductions
     * @return void
     * @throws Throwable
     */
    private function processPostPaidTransaction($user, MpesaTransaction $mpesa_transaction, $deductions): void
    {
        $request = new CreateMeterBillingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $user->meter_id,
            'amount_paid' => $mpesa_transaction->TransAmount,
            'deductions' => $deductions,
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
        $user_total_amount = $this->calculateUserTotalAmount($user->account_balance, $request->amount_paid, $request->deductions);
        \Log::info('User total amount: '. $user_total_amount);
        \Log::info('User account balance: '. $user->account_balance);
        if ($user_total_amount <= 0){
            \Log::info('Amount exhausted');
            return response()->json('Amount exhausted', 422);
        }
        $pending_meter_readings = MeterReading::where('meter_id', $request->meter_id)
            ->where(function ($query) {
                $query->where('status', PaymentStatus::NOT_PAID);
                $query->orWhere('status', PaymentStatus::PARTIALLY_PAID);
            })
            ->orderBy('created_at', 'ASC')->get();

        \Log::info('Pending meter readings count: '. $pending_meter_readings->count());

        if ($pending_meter_readings->count() === 0) {
            if ($request->deductions->monthly_service_charge_deducted === 0 && $request->deductions->connection_fee_deducted === 0 && $request->deductions->unaccounted_debt_deducted === 0) {
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
