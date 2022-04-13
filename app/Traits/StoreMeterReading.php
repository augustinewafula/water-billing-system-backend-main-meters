<?php

namespace App\Traits;

use App\Http\Requests\CreateMeterBillingRequest;
use App\Http\Requests\CreateMeterReadingRequest;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterReading;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Log;
use Throwable;

trait StoreMeterReading
{
    use CalculatesBill, StoreMeterBillings;

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateMeterReadingRequest $request
     * @return Application|ResponseFactory|JsonResponse|Response
     * @throws Throwable
     */
    public function store(CreateMeterReadingRequest $request)
    {
        $meter = Meter::find($request->meter_id);

        $next_month = Carbon::parse($request->month)->add(1, 'month')->format('M');
        $bill_due_days = Setting::where('key', 'bill_due_days')
            ->first()
            ->value;
        $delay_meter_reading_sms = Setting::where('key', 'delay_meter_reading_sms')
            ->first()
            ->value;
        $send_sms_at = Carbon::now()->toDateTimeString();
        if ($delay_meter_reading_sms) {
            $meter_reading_sms_delay_days = Setting::where('key', 'meter_reading_sms_delay_days')
                ->first()
                ->value;
            $send_sms_at = Carbon::now()->add($meter_reading_sms_delay_days, 'day')->toDateTimeString();
        }

        $due_date = Carbon::parse($bill_due_days . 'th ' . $next_month)->toDateTimeString();

        try {
            DB::beginTransaction();
            $bill = $this->calculateBill($meter->last_reading, $request->current_reading);
            $service_fee = $this->calculateServiceFee($bill, 'post-pay');
            $previous_meter_reading = MeterReading::where('meter_id', $meter->id)->latest()->first();
            $meter_reading = MeterReading::create([
                'meter_id' => $request->meter_id,
                'previous_reading' => $meter->last_reading,
                'current_reading' => $request->current_reading,
                'month' => $request->month,
                'bill' => $bill + $service_fee,
                'service_fee' => $service_fee,
                'send_sms_at' => $send_sms_at,
                'bill_due_at' => $due_date,
            ]);
            $meter->update([
                'last_reading' => $request->current_reading,
                'last_reading_date' => Carbon::now()->toDateTimeString(),
            ]);
            $this->processAvailableCredits($meter, $meter_reading, $previous_meter_reading);
            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th);
            $response = ['message' => 'Something went wrong, please contact website admin for help'];
            return response($response, 422);
        }
        return response()->json($bill, 201);

    }

    public function processAvailableCredits($meter, $meter_reading, $previous_meter_reading): void
    {
        $user = User::where('meter_id', $meter->id)->first();
        if ($this->userHasAccountBalance($user)) {
            $request = new CreateMeterBillingRequest();
            $request->setMethod('POST');
            $request->request->add([
                'meter_id' => $meter->id,
                'amount_paid' => 0
            ]);
            $last_meter_billing = MeterBilling::where('meter_reading_id', $previous_meter_reading->id)->latest()->first();
            $this->processMeterBillings($request, [$meter_reading], $user, $last_meter_billing->mpesa_transaction_id);
        }
    }

    public function userHasAccountBalance($user): bool
    {
        return $user->account_balance > 0;
    }

}
