<?php

namespace App\Traits;

use App\Http\Requests\CreateMeterReadingRequest;
use App\Jobs\SendSMS;
use App\Models\Meter;
use App\Models\MeterReading;
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
    use calculateBill;
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
        $bill = $this->calculateBill($meter->last_reading, $request->current_reading);

        try {
            DB::beginTransaction();
            $previous_reading = $meter->last_reading;
            MeterReading::create([
                'meter_id' => $request->meter_id,
                'previous_reading' => $meter->last_reading,
                'current_reading' => $request->current_reading,
                'month' => $request->month,
                'bill' => $bill,
                'service_fee' => $this->calculateServiceFee($bill, 'post-pay'),
                'send_sms_at' => Carbon::now()->add(2, 'day')->toDateTimeString()
            ]);
            $meter->update([
                'last_reading' => $request->current_reading,
                'last_reading_date' => Carbon::now()->toDateTimeString(),
            ]);
            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th);
            $response = ['message' => 'Something went wrong, please contact website admin for help'];
            return response($response, 422);
        }
//        $this->sendMeterReadingsToUser($meter, $request, $bill, $previous_reading);
        return response()->json($bill, 201);

    }

    public function sendMeterReadingsToUser($meter, $request, $bill, $previous_reading): bool
    {
        $user = User::where('meter_id', $meter->id)
            ->first();
        if (!$user) {
            return false;
        }
        $user_name = ucwords($user->name);
        $next_month = Carbon::now()->add(1, 'month')->format('M');
        $due_date = Carbon::parse('4th ' . $next_month)->format('d/m/Y');
        $current_month = Carbon::now()->isoFormat('MMMM YYYY');
        $units_consumed = $request->current_reading - $previous_reading;
        $message = "Hello $user_name, your water billing for $current_month is as follows:\nReading: $request->current_reading\nPrevious reading: $previous_reading\nUnits consumed: $units_consumed\nBill: Ksh $bill\nBalance brought forward: Ksh $user->account_balance\nDue date: $due_date\nPay via paybill number 994470, account number $meter->number";
        SendSMS::dispatch($user->phone, $message);
        return true;
    }
}
