<?php

namespace App\Traits;

use App\Http\Requests\CreateMeterBillingRequest;
use App\Http\Requests\CreateMeterReadingRequest;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterReading;
use App\Models\MonthlyServiceCharge;
use App\Models\MonthlyServiceChargePayment;
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

trait StoresMeterReading
{
    use CalculatesBill, StoresMeterBillings;

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
        $bill_due_on = Setting::where('key', 'bill_due_on')
            ->first()
            ->value;
        $tell_user_meter_disconnection_on = Setting::where('key', 'tell_user_meter_disconnection_on')
            ->first()
            ->value;
        $actual_meter_disconnection_on = Setting::where('key', 'actual_meter_disconnection_on')
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

        $bill_due_on = Carbon::parse($bill_due_on . 'th ' . $next_month)->startOfDay()->addHours(7)->toDateTimeString();
        $tell_user_meter_disconnection_on = Carbon::parse($tell_user_meter_disconnection_on . 'th ' . $next_month)->startOfDay()->addHours(7)->toDateTimeString();
        $actual_meter_disconnection_on = Carbon::parse($actual_meter_disconnection_on . 'th ' . $next_month)->startOfDay()->addHours(7)->toDateTimeString();

        try {
            DB::beginTransaction();
            $bill = $this->calculateBill($meter->last_reading, $request->current_reading);
            $service_fee = $this->calculateServiceFee($bill, 'post-pay');
            $meter_reading = MeterReading::create([
                'meter_id' => $request->meter_id,
                'previous_reading' => $meter->last_reading,
                'current_reading' => $request->current_reading,
                'month' => $request->month,
                'bill' => $bill + $service_fee,
                'service_fee' => $service_fee,
                'send_sms_at' => $send_sms_at,
                'bill_due_at' => $bill_due_on,
                'tell_user_meter_disconnection_on' => $tell_user_meter_disconnection_on,
                'actual_meter_disconnection_on' => $actual_meter_disconnection_on,
            ]);
            $meter->update([
                'last_reading' => $request->current_reading,
                'last_reading_date' => Carbon::now()->toDateTimeString(),
            ]);
            $user = User::where('meter_id', $meter->id)->first();
            if ($user){
                if ($user->account_balance <= 0){
                    $user->update([
                        'account_balance' => ($user->account_balance - ($bill + $service_fee))
                    ]);
                }
                $this->processAvailableCredits($user, $meter_reading);
            }
            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th);
            $response = ['message' => 'Something went wrong, please contact website admin for help'];
            return response($response, 422);
        }
        return response()->json($bill, 201);

    }

    /**
     * @throws Throwable
     */
    public function processAvailableCredits($user, $meter_reading): void
    {
        throw_if($user === null, 'RuntimeException', 'Meter user not found');
        if ($this->userHasAccountBalance($user)) {
            $request = new CreateMeterBillingRequest();
            $request->setMethod('POST');
            $request->request->add([
                'meter_id' => $user->meter_id,
                'amount_paid' => 0,
                'monthly_service_charge_deducted' => 0,
            ]);

            $this->processMeterBillings($request, [$meter_reading], $user, $user->last_mpesa_transaction_id);
        }
    }

    public function userHasAccountBalance($user): bool
    {
        return $user->account_balance > 0;
    }

}
